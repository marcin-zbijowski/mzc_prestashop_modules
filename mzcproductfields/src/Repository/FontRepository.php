<?php

declare(strict_types=1);

namespace MZConsulting\MzcProductFields\Repository;

use MzcProductFieldsFont;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Handles uploaded custom fonts: validation, storage under
 * views/fonts/uploads/, and the DB rows backing the "Preview font" dropdown
 * on the field CRUD screen (see FieldType/FieldController).
 */
final class FontRepository
{
    /**
     * @return array<int,array{id_font:int,name:string,font_family:string,original_filename:string}>
     */
    public function findAllForGrid(): array
    {
        return MzcProductFieldsFont::listAll();
    }

    /**
     * @return array<int,string> font_family => name, for the field form's "Preview font" choices
     */
    public function getChoices(): array
    {
        $choices = [];
        foreach (MzcProductFieldsFont::listAll() as $row) {
            $choices[$row['font_family']] = $row['name'];
        }

        return $choices;
    }

    /**
     * Validates and stores an uploaded font file, creating its DB row.
     *
     * @return string|null an error message, or null on success
     */
    public function upload(string $name, ?UploadedFile $file): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return 'Please enter a name for this font.';
        }

        if (!$file) {
            return 'Please choose a font file to upload.';
        }

        if (!$file->isValid()) {
            return 'The uploaded file could not be read.';
        }

        if ($file->getSize() > MzcProductFieldsFont::MAX_FILE_SIZE) {
            return 'The font file is too large (max 2 MB).';
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, MzcProductFieldsFont::ALLOWED_EXTENSIONS, true)) {
            return 'Unsupported file type. Allowed: ' . implode(', ', MzcProductFieldsFont::ALLOWED_EXTENSIONS) . '.';
        }

        $font = new MzcProductFieldsFont();
        $font->name = $name;
        $font->original_filename = $file->getClientOriginalName();
        // Placeholders, filled in once we have the row's id (needed for a
        // collision-free filename and a guaranteed-unique font-family).
        $font->font_family = 'mzc-font-pending';
        $font->filename = 'pending';
        $font->date_add = date('Y-m-d H:i:s');

        if (!$font->add()) {
            return 'Could not save the font — please try again.';
        }

        // Use $font->id (ObjectModel's generic PK accessor), not $font->id_font:
        // only $id is guaranteed populated by add() in this same request — the
        // specifically-named property isn't synced until the row is reloaded.
        $fontFamily = 'mzc-font-' . (int) $font->id;
        $filename = (int) $font->id . '.' . $extension;

        $uploadDir = MzcProductFieldsFont::getUploadDir();
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            $font->delete();

            return 'Could not create the fonts directory on the server.';
        }

        try {
            $file->move($uploadDir, $filename);
        } catch (\Exception $e) {
            $font->delete();

            return 'Could not store the uploaded file: ' . $e->getMessage();
        }

        $font->font_family = $fontFamily;
        $font->filename = $filename;
        $font->update();

        return null;
    }

    public function delete(int $idFont): bool
    {
        if ($idFont <= 0) {
            return false;
        }

        $font = new MzcProductFieldsFont($idFont);
        if (!$font->id) {
            return false;
        }

        $path = MzcProductFieldsFont::getUploadDir() . $font->filename;
        if (is_file($path)) {
            @unlink($path);
        }

        return (bool) $font->delete();
    }
}
