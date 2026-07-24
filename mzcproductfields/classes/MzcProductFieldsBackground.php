<?php
/**
 * MZC Product Fields — uploaded preview background image ObjectModel.
 *
 * One row = one uploaded image (e.g. "Front", "Back") that a field's live
 * preview can be anchored to instead of the theme's own product photo — see
 * Service/ProductFieldsPresenter's docblock. The physical file lives in
 * views/backgrounds/uploads/, a plain web-servable directory, mirroring how
 * fonts are stored.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class MzcProductFieldsBackground extends ObjectModel
{
    /** @var array<int,string> accepted image file extensions */
    const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /** @var int max upload size in bytes (5 MB) */
    const MAX_FILE_SIZE = 5 * 1024 * 1024;

    /** @var int */
    public $id_background;

    /** @var string */
    public $name;

    /** @var string filename on disk under views/backgrounds/uploads/ */
    public $filename;

    /** @var string original uploaded filename, for display only */
    public $original_filename;

    /** @var string */
    public $date_add;

    public static $definition = [
        'table' => 'mzc_productfields_background',
        'primary' => 'id_background',
        'multilang' => false,
        'fields' => [
            'name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 100],
            'filename' => ['type' => self::TYPE_STRING, 'validate' => 'isFileName', 'required' => true, 'size' => 255],
            // isGenericName, not isFileName — see MzcProductFieldsFont's
            // identical reasoning: this is display-only, the physical
            // filename is always separately generated (see
            // BackgroundRepository::upload()).
            'original_filename' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 255],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true],
        ],
    ];

    /**
     * @return array<int,array> all uploaded backgrounds, most recent first
     */
    public static function listAll(): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'mzc_productfields_background` ORDER BY `id_background` DESC'
        );

        return $rows ?: [];
    }

    /**
     * @return string absolute filesystem path to the uploads directory
     */
    public static function getUploadDir(): string
    {
        return _PS_MODULE_DIR_ . 'mzcproductfields/views/backgrounds/uploads/';
    }
}
