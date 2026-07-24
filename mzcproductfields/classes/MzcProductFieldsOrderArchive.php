<?php
/**
 * MZC Product Fields — archived historical personalization order data.
 *
 * This table is intentionally NOT touched by this module's own uninstall().
 * It exists to receive a permanent copy of Anvanto's `an_productfields_order`
 * / `_order_lang` rows (348 rows confirmed in the live copy, 2024-01-22 to
 * 2025-01-27) before any Anvanto uninstall is even considered — Anvanto's own
 * sql/uninstall.php DROPs those tables, which would otherwise destroy this
 * history permanently. See classes/MzcProductFieldsMigrator.php for the writer.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class MzcProductFieldsOrderArchive extends ObjectModel
{
    /** @var int */
    public $id_mzc_productfields_order_archive;

    /** @var int|null best-effort link to the source Anvanto field id — informational only */
    public $source_id_field;

    /** @var int|null Anvanto `an_productfields_order.id_pf_order` this row was migrated from — the true natural key for re-run idempotency (id_order/id_order_detail/id_product/id_customization alone can repeat: multiple fields on one order line commonly share id_customization=0, since Anvanto doesn't use native Customization) */
    public $source_pf_order_id;

    /** @var string free-form provenance marker, e.g. "an_productfields" */
    public $source;

    /** @var int */
    public $id_cart;

    /** @var int */
    public $id_order;

    /** @var int */
    public $id_order_detail;

    /** @var int */
    public $id_product;

    /** @var int */
    public $id_product_attribute;

    /** @var int */
    public $id_customization;

    /** @var int */
    public $qty;

    /** @var string decimal(20,6) as string */
    public $price;

    /** @var string decimal(20,6) as string */
    public $price_wt;

    /** @var string */
    public $date_add;

    /** @var array<int,array{title:string,title_value:string}> id_lang => snapshot */
    public $lang_data;

    public static $definition = [
        'table' => 'mzc_productfields_order_archive',
        'primary' => 'id_mzc_productfields_order_archive',
        'multilang' => false,
        'fields' => [
            'source_id_field' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'source_pf_order_id' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'source' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 30],
            'id_cart' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_order' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_order_detail' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_product' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_product_attribute' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'id_customization' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'qty' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'price' => ['type' => self::TYPE_STRING, 'validate' => 'isPrice'],
            'price_wt' => ['type' => self::TYPE_STRING, 'validate' => 'isPrice'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true],
        ],
    ];

    /**
     * Persist the per-language title/value snapshot rows for this archive row.
     * Called after add()/update() since this table isn't declared 'multilang'
     * in the ObjectModel definition (its lang rows are a free-form snapshot,
     * not a translatable field of this entity).
     */
    public function saveLangData(array $langRows): bool
    {
        if (!$this->id) {
            return false;
        }

        Db::getInstance()->delete('mzc_productfields_order_archive_lang', '`id_mzc_productfields_order_archive` = ' . (int) $this->id);

        $ok = true;
        foreach ($langRows as $idLang => $row) {
            $ok = $ok && Db::getInstance()->insert('mzc_productfields_order_archive_lang', [
                'id_mzc_productfields_order_archive' => (int) $this->id,
                'id_lang' => (int) $idLang,
                'title' => pSQL((string) $row['title']),
                'title_value' => pSQL((string) $row['title_value'], true),
            ]);
        }

        return $ok;
    }

    /**
     * @return array<int,array> rows for one order, joined with their per-language snapshots for $idLang
     */
    public static function getForOrder(int $idOrder, int $idLang): array
    {
        if ($idOrder <= 0) {
            return [];
        }

        $sql = 'SELECT a.*, al.`title`, al.`title_value`
                FROM `' . _DB_PREFIX_ . 'mzc_productfields_order_archive` a
                LEFT JOIN `' . _DB_PREFIX_ . 'mzc_productfields_order_archive_lang` al
                    ON al.`id_mzc_productfields_order_archive` = a.`id_mzc_productfields_order_archive`
                    AND al.`id_lang` = ' . (int) $idLang . '
                WHERE a.`id_order` = ' . (int) $idOrder . '
                ORDER BY a.`id_order_detail` ASC, a.`id_mzc_productfields_order_archive` ASC';

        return Db::getInstance()->executeS($sql) ?: [];
    }
}
