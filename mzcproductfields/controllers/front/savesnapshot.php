<?php
/**
 * MZC Product Fields — front-office AJAX endpoint that stores the "what the
 * customer saw" JPG snapshot generated client-side by views/js/front-snapshot.js
 * right after the content-confirmation step (see savecustomization.php) saves
 * the personalization. Called fire-and-forget: the add-to-cart flow never
 * waits on this, so any failure here silently just means no backoffice
 * thumbnail for that line, not a broken checkout.
 *
 * A plain legacy ModuleFrontController, not a Symfony route — same reasoning
 * as savecustomization.php (front-office requests never boot a Symfony
 * kernel).
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class MzcproductfieldsSaveSnapshotModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent(): void
    {
        parent::initContent();

        $idCustomization = (int) Tools::getValue('id_customization');
        $file = $_FILES['snapshot'] ?? null;

        if ($idCustomization <= 0 || !$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->renderJson(['success' => false]);

            return;
        }

        // Ownership check: the customization being snapshotted must belong to
        // THIS visitor's own cart — otherwise anyone could POST an arbitrary
        // id_customization and attach an image to someone else's order line.
        $customization = new Customization($idCustomization);
        if (!$customization->id || (int) $customization->id_cart !== (int) $this->context->cart->id) {
            $this->renderJson(['success' => false]);

            return;
        }

        if ($file['size'] > MzcProductFieldsOrderSnapshot::MAX_FILE_SIZE) {
            $this->renderJson(['success' => false]);

            return;
        }

        // Trust the actual file bytes, not the client-supplied extension/MIME
        // type — getimagesize() reads the file's real signature. Deliberately
        // not exif_imagetype(): it needs the optional exif extension, which
        // isn't installed on every target environment (confirmed missing on
        // this project's own PS9 docker image), while getimagesize() is core
        // PHP and does the same signature check.
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false || $imageInfo[2] !== IMAGETYPE_JPEG) {
            $this->renderJson(['success' => false]);

            return;
        }

        $uploadDir = MzcProductFieldsOrderSnapshot::getUploadDir();
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            $this->renderJson(['success' => false]);

            return;
        }

        $filename = $idCustomization . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            $this->renderJson(['success' => false]);

            return;
        }

        $snapshot = new MzcProductFieldsOrderSnapshot();
        $snapshot->id_customization = $idCustomization;
        $snapshot->filename = $filename;
        $snapshot->date_add = date('Y-m-d H:i:s');

        if (!$snapshot->add()) {
            @unlink($uploadDir . $filename);
            $this->renderJson(['success' => false]);

            return;
        }

        $this->renderJson(['success' => true]);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function renderJson(array $data): void
    {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
