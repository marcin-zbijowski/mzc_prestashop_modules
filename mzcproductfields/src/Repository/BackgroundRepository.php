<?php

declare(strict_types=1);

namespace MZConsulting\MzcProductFields\Repository;

use Context;
use MzcProductFieldsBackground;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Handles uploaded preview background images: validation, storage under
 * views/backgrounds/uploads/, and the DB rows backing the "Preview
 * background image" dropdown on the field CRUD screen (see
 * FieldType/FieldController) and the front-office live preview (see
 * ProductFieldsPresenter).
 */
final class BackgroundRepository
{
    /**
     * @return array<int,array{id_background:int,name:string,original_filename:string,filename:string}>
     */
    public function findAllForGrid(): array
    {
        return MzcProductFieldsBackground::listAll();
    }

    /**
     * @return array<int,string> id_background => name, for the field form's "Preview background image" choices
     */
    public function getChoices(): array
    {
        $choices = [];
        foreach (MzcProductFieldsBackground::listAll() as $row) {
            $choices[(int) $row['id_background']] = $row['name'];
        }

        return $choices;
    }

    public function getUrl(int $idBackground): ?string
    {
        if ($idBackground <= 0) {
            return null;
        }

        $background = new MzcProductFieldsBackground($idBackground);
        if (!$background->id) {
            return null;
        }

        return rtrim(Context::getContext()->shop->getBaseURL(true), '/')
            . '/modules/mzcproductfields/views/backgrounds/uploads/' . $background->filename;
    }

    /**
     * Validates and stores an uploaded background image, creating its DB row.
     *
     * @return string|null an error message, or null on success
     */
    public function upload(string $name, ?UploadedFile $file): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return 'Please enter a name for this background image.';
        }

        if (!$file) {
            return 'Please choose an image to upload.';
        }

        if (!$file->isValid()) {
            return 'The uploaded file could not be read.';
        }

        if ($file->getSize() > MzcProductFieldsBackground::MAX_FILE_SIZE) {
            return 'The image file is too large (max 5 MB).';
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, MzcProductFieldsBackground::ALLOWED_EXTENSIONS, true)) {
            return 'Unsupported file type. Allowed: ' . implode(', ', MzcProductFieldsBackground::ALLOWED_EXTENSIONS) . '.';
        }

        $background = new MzcProductFieldsBackground();
        $background->name = $name;
        $background->original_filename = $file->getClientOriginalName();
        // Placeholder, filled in once we have the row's id (needed for a
        // collision-free filename) — same pattern as FontRepository::upload().
        $background->filename = 'pending';
        $background->date_add = date('Y-m-d H:i:s');

        if (!$background->add()) {
            return 'Could not save the background image — please try again.';
        }

        $filename = (int) $background->id . '.' . $extension;

        $uploadDir = MzcProductFieldsBackground::getUploadDir();
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            $background->delete();

            return 'Could not create the backgrounds directory on the server.';
        }

        try {
            $file->move($uploadDir, $filename);
        } catch (\Exception $e) {
            $background->delete();

            return 'Could not store the uploaded file: ' . $e->getMessage();
        }

        $background->filename = $filename;
        $background->update();

        return null;
    }

    public function delete(int $idBackground): bool
    {
        if ($idBackground <= 0) {
            return false;
        }

        $background = new MzcProductFieldsBackground($idBackground);
        if (!$background->id) {
            return false;
        }

        $path = MzcProductFieldsBackground::getUploadDir() . $background->filename;
        if (is_file($path)) {
            @unlink($path);
        }

        return (bool) $background->delete();
    }
}
