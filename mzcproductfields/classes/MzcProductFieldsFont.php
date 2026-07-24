<?php
/**
 * MZC Product Fields — uploaded custom font ObjectModel.
 *
 * One row = one uploaded font file made available in the "Preview font"
 * dropdown on the field CRUD screen. `font_family` is a generated, CSS-safe,
 * unique identifier (e.g. "mzc-font-3") — not the user-supplied display
 * name — so it's always a valid `font-family` value with no escaping or
 * collision concerns; `name` is what the admin actually typed, shown in the
 * dropdown label. The physical file lives in views/fonts/uploads/, a plain
 * web-servable directory (no .htaccess restrictions), since front-office
 * @font-face declarations need a working public URL to it.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class MzcProductFieldsFont extends ObjectModel
{
    /** @var array<int,string> accepted font file extensions */
    const ALLOWED_EXTENSIONS = ['ttf', 'otf', 'woff', 'woff2'];

    /** @var int max upload size in bytes (2 MB) */
    const MAX_FILE_SIZE = 2 * 1024 * 1024;

    /** @var int */
    public $id_font;

    /** @var string */
    public $name;

    /** @var string CSS-safe generated identifier, e.g. "mzc-font-3" */
    public $font_family;

    /** @var string filename on disk under views/fonts/uploads/ */
    public $filename;

    /** @var string original uploaded filename, for display only */
    public $original_filename;

    /** @var string */
    public $date_add;

    public static $definition = [
        'table' => 'mzc_productfields_font',
        'primary' => 'id_font',
        'multilang' => false,
        'fields' => [
            'name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 100],
            'font_family' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 50],
            'filename' => ['type' => self::TYPE_STRING, 'validate' => 'isFileName', 'required' => true, 'size' => 255],
            // isGenericName, not isFileName: this is a display-only value (the
            // physical file is always stored under a separately generated,
            // guaranteed-safe name — see FontRepository::upload()), and real
            // font file names routinely contain spaces/parentheses (e.g.
            // "Apple Chancery.ttf"), which isFileName's [a-zA-Z0-9_.-] pattern
            // rejects outright.
            'original_filename' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 255],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true],
        ],
    ];

    /**
     * @return array<int,array> all uploaded fonts, most recent first
     */
    public static function listAll(): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'mzc_productfields_font` ORDER BY `id_font` DESC'
        );

        return $rows ?: [];
    }

    /**
     * @return string absolute filesystem path to the uploads directory
     */
    public static function getUploadDir(): string
    {
        return _PS_MODULE_DIR_ . 'mzcproductfields/views/fonts/uploads/';
    }
}
