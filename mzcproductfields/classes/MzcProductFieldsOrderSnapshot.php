<?php
/**
 * MZC Product Fields — order-visualization JPG snapshot ObjectModel.
 *
 * One row = one JPG captured client-side (see views/js/front-snapshot.js) at
 * content-confirmation time, showing exactly what the customer's background +
 * typed text looked like. Linked to the native `customization` table (not our
 * own field tables), so it resolves to an order via
 * `customization.id_cart` = `orders.id_cart` — see getForOrder(). A single
 * customization can have more than one snapshot (e.g. distinct "Front"/"Back"
 * preview boxes both had content), hence a dedicated table rather than a
 * single column on `customization`.
 *
 * Deliberately excluded from MzcProductFieldsInstaller::uninstallDb() and from
 * Mzcproductfields::uninstall()'s upload cleanup — see the installer's
 * docblock for why these must survive an unrelated later uninstall.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class MzcProductFieldsOrderSnapshot extends ObjectModel
{
    /** @var int max upload size in bytes (3 MB — a composited JPG, not a raw upload) */
    const MAX_FILE_SIZE = 3 * 1024 * 1024;

    /** @var int */
    public $id_order_snapshot;

    /** @var int */
    public $id_customization;

    /** @var string filename on disk under views/order_snapshots/uploads/ */
    public $filename;

    /** @var string */
    public $date_add;

    public static $definition = [
        'table' => 'mzc_productfields_order_snapshot',
        'primary' => 'id_order_snapshot',
        'multilang' => false,
        'fields' => [
            'id_customization' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'filename' => ['type' => self::TYPE_STRING, 'validate' => 'isFileName', 'required' => true, 'size' => 255],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true],
        ],
    ];

    /**
     * @return array<int,array{id_order_snapshot:int,id_customization:int,filename:string,date_add:string,id_product:int,product_name:string}>
     */
    public static function getForOrder(int $idOrder, int $idLang): array
    {
        if ($idOrder <= 0) {
            return [];
        }

        // product_name included so the admin order block can label each
        // thumbnail — confirmed a real gap: with more than one personalized
        // product on the same order, the grid of images alone gave no way to
        // tell which preview belonged to which product.
        $sql = 'SELECT os.*, c.`id_product`, pl.`name` AS product_name
                FROM `' . _DB_PREFIX_ . 'mzc_productfields_order_snapshot` os
                INNER JOIN `' . _DB_PREFIX_ . 'customization` c ON c.id_customization = os.id_customization
                INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_cart = c.id_cart
                INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.id_product = c.id_product AND pl.id_lang = ' . (int) $idLang . '
                WHERE o.id_order = ' . (int) $idOrder . '
                ORDER BY c.`id_product` ASC, os.id_order_snapshot ASC';

        $rows = Db::getInstance()->executeS($sql);

        return $rows ?: [];
    }

    /**
     * @return string absolute filesystem path to the uploads directory
     */
    public static function getUploadDir(): string
    {
        return _PS_MODULE_DIR_ . 'mzcproductfields/views/order_snapshots/uploads/';
    }

    /**
     * @return string public URL for a stored snapshot's filename
     */
    public static function getUrl(string $filename): string
    {
        return rtrim(Context::getContext()->shop->getBaseURL(true), '/')
            . '/modules/mzcproductfields/views/order_snapshots/uploads/' . $filename;
    }
}
