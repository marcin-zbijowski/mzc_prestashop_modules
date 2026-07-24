<?php
/**
 * MZC Product Fields — field definition ObjectModel.
 *
 * One row = one personalization field, scoped to all products, a single
 * category, or one-or-more individual products (see
 * `mzc_productfields_field_product` for the product association, managed via
 * FieldRepository — not a single `scope_id`). Price precision is DECIMAL(20,6)
 * — matching PrestaShop's own `product.price` column — so net/gross/VAT
 * calculations never lose precision to premature rounding (Anvanto stored
 * decimal(12,2), which is the source of its observed rounding artifacts, e.g.
 * landing on 14.99 instead of the exact computed value).
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class MzcProductFieldsField extends ObjectModel
{
    /** Field applies to every product in the catalog. */
    const SCOPE_ALL = 0;
    /** Field applies to every product in one category (`scope_id` = id_category). */
    const SCOPE_CATEGORY = 1;
    /** Field applies to one or more individually chosen products (see `mzc_productfields_field_product`, not `scope_id`). */
    const SCOPE_PRODUCTS = 2;

    const PRICE_TYPE_NET = 0;
    const PRICE_TYPE_GROSS = 1;

    const TYPE_TEXT = 'text';
    const TYPE_TEXTAREA = 'textarea';
    const TYPE_DATE = 'date';
    const TYPE_CHECKBOX = 'checkbox';
    const TYPE_RADIO = 'radio';
    const TYPE_SELECT = 'select';
    const TYPE_MULTISELECT = 'multiselect';

    /** @var array<string> all supported field types (see section 2 of the plan) */
    const FIELD_TYPES = [
        self::TYPE_TEXT,
        self::TYPE_TEXTAREA,
        self::TYPE_DATE,
        self::TYPE_CHECKBOX,
        self::TYPE_RADIO,
        self::TYPE_SELECT,
        self::TYPE_MULTISELECT,
    ];

    /** @var array<string> field types that carry their own priced options (mzc_productfields_field_value) */
    const TYPES_WITH_VALUES = [
        self::TYPE_CHECKBOX,
        self::TYPE_RADIO,
        self::TYPE_SELECT,
        self::TYPE_MULTISELECT,
    ];

    /** @var int */
    public $id_mzc_productfields_field;

    /** @var int self::SCOPE_* */
    public $scope_type;

    /** @var int|null id_category when scope_type = SCOPE_CATEGORY, otherwise null (product membership lives in mzc_productfields_field_product) */
    public $scope_id;

    /** @var string self::TYPE_* */
    public $field_type;

    /** @var int 0|1 */
    public $required;

    /** @var int 0|1 */
    public $is_paid;

    /** @var int self::PRICE_TYPE_* */
    public $price_type;

    /** @var string decimal(20,6) as string to avoid float precision loss */
    public $price_amount;

    /** @var int|null */
    public $id_tax_rules_group;

    /** @var int|null */
    public $max_length;

    /** @var int 0|1 shows a "X/max_length" counter under the input on the front office — only meaningful when max_length is set */
    public $char_counter;

    /** @var int */
    public $position;

    /** @var int 0|1 */
    public $active;

    /** @var string|null percentage 0-100, position of preview overlay from the left */
    public $preview_pos_x;

    /** @var string|null percentage 0-100, position of preview overlay from the top */
    public $preview_pos_y;

    /** @var string|null CSS font-family for the preview overlay */
    public $preview_font;

    /** @var string|null font size for the preview overlay, as a percentage of the background image's rendered width */
    public $preview_font_size;

    /** @var string|null max width the preview text is allowed to wrap within, as a percentage of the background image's (or its inset overlay's) rendered width — null means "use the full available width" */
    public $preview_max_width;

    /** @var string|null hex color for the preview overlay text */
    public $preview_color;

    /** @var int|null uploaded background image (MzcProductFieldsBackground) the live preview anchors to instead of the theme's product photo — only ever consulted when is_front_field is false, see that property's docblock */
    public $id_background;

    /**
     * @var bool defaults to true (a "front" field). When true, the live
     *      preview ALWAYS overlays the theme's own product photo, regardless
     *      of id_background or any active combination rule — this is what a
     *      field meant for the front of the item (e.g. a name printed on the
     *      poster itself) needs. Set to false for a field meant for the
     *      reverse/back of the item (e.g. engraving on the back of a frame),
     *      so it renders on the background image system (its own static
     *      id_background, or whichever background an active combination rule
     *      points to) instead. Added after a real bug: before this flag
     *      existed, EVERY positioned field on a product got swept into
     *      whichever box a combination rule matched (dynamicMode is a
     *      per-product switch, not per-field — see
     *      ProductFieldsPresenter::attachBackgroundsToFields()'s docblock),
     *      so a front-facing field and a back-facing field on the same
     *      product both rendered on the SAME background whenever one
     *      happened to be configured, even though only the back-facing one
     *      was ever meant to.
     */
    public $is_front_field;

    /** @var int|null Anvanto `an_productfields_fields.id_field` this was migrated from — lets the migrator re-run without creating duplicates; null for fields created natively in this module */
    public $source_an_field_id;

    /** @var string */
    public $date_add;

    /** @var string */
    public $date_upd;

    /** @var array<int,string> id_lang => label */
    public $label;

    /** @var array<int,string> id_lang => placeholder */
    public $placeholder;

    public static $definition = [
        'table' => 'mzc_productfields_field',
        'primary' => 'id_mzc_productfields_field',
        'multilang' => true,
        'fields' => [
            'scope_type' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'scope_id' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'allow_null' => true],
            'field_type' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 20],
            'required' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'is_paid' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'price_type' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'price_amount' => ['type' => self::TYPE_STRING, 'validate' => 'isPrice'],
            'id_tax_rules_group' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'allow_null' => true],
            'max_length' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'allow_null' => true],
            'char_counter' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'position' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'preview_pos_x' => ['type' => self::TYPE_STRING, 'validate' => 'isFloat', 'allow_null' => true],
            'preview_pos_y' => ['type' => self::TYPE_STRING, 'validate' => 'isFloat', 'allow_null' => true],
            'preview_font' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 100, 'allow_null' => true],
            'preview_font_size' => ['type' => self::TYPE_STRING, 'validate' => 'isFloat', 'allow_null' => true],
            'preview_max_width' => ['type' => self::TYPE_STRING, 'validate' => 'isFloat', 'allow_null' => true],
            'preview_color' => ['type' => self::TYPE_STRING, 'validate' => 'isColor', 'size' => 7, 'allow_null' => true],
            'id_background' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'allow_null' => true],
            'is_front_field' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'source_an_field_id' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'allow_null' => true],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'label' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'required' => true, 'size' => 255],
            'placeholder' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'size' => 255],
        ],
    ];

    /**
     * Resolve the fields that apply to a given product: fields individually
     * assigned to it via `mzc_productfields_field_product` (SCOPE_PRODUCTS),
     * plus SCOPE_CATEGORY fields for any of its categories, plus every
     * SCOPE_ALL field. Ordered by position.
     *
     * @return array<int,array> raw rows (id_mzc_productfields_field, ...)
     */
    public static function getFieldsForProduct(int $idProduct, int $idLang, bool $activeOnly = true): array
    {
        if ($idProduct <= 0) {
            return [];
        }

        $categoryIds = Db::getInstance()->executeS(
            'SELECT `id_category` FROM `' . _DB_PREFIX_ . 'category_product`
             WHERE `id_product` = ' . (int) $idProduct
        );
        $categoryIds = $categoryIds ? array_map(static function ($row) {
            return (int) $row['id_category'];
        }, $categoryIds) : [];

        $conditions = ['f.`scope_type` = ' . self::SCOPE_ALL];
        $conditions[] = 'f.`scope_type` = ' . self::SCOPE_PRODUCTS . ' AND fp.`id_product` = ' . (int) $idProduct;
        if ($categoryIds) {
            $conditions[] = 'f.`scope_type` = ' . self::SCOPE_CATEGORY . ' AND f.`scope_id` IN (' . implode(',', $categoryIds) . ')';
        }

        $sql = 'SELECT f.*, fl.`label`, fl.`placeholder`
                FROM `' . _DB_PREFIX_ . 'mzc_productfields_field` f
                INNER JOIN `' . _DB_PREFIX_ . 'mzc_productfields_field_lang` fl
                    ON fl.`id_mzc_productfields_field` = f.`id_mzc_productfields_field`
                    AND fl.`id_lang` = ' . (int) $idLang . '
                LEFT JOIN `' . _DB_PREFIX_ . 'mzc_productfields_field_product` fp
                    ON fp.`id_mzc_productfields_field` = f.`id_mzc_productfields_field`
                WHERE (' . implode(') OR (', $conditions) . ')'
                . ($activeOnly ? ' AND f.`active` = 1' : '')
                . ' GROUP BY f.`id_mzc_productfields_field`
                ORDER BY f.`position` ASC, f.`id_mzc_productfields_field` ASC';

        return Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * All field definitions for the admin listing, most recent first.
     * `product_count` is only meaningful when scope_type = SCOPE_PRODUCTS.
     */
    public static function listAll(int $idLang): array
    {
        $sql = 'SELECT f.*, fl.`label`, COUNT(fp.`id_product`) AS product_count
                FROM `' . _DB_PREFIX_ . 'mzc_productfields_field` f
                INNER JOIN `' . _DB_PREFIX_ . 'mzc_productfields_field_lang` fl
                    ON fl.`id_mzc_productfields_field` = f.`id_mzc_productfields_field`
                    AND fl.`id_lang` = ' . (int) $idLang . '
                LEFT JOIN `' . _DB_PREFIX_ . 'mzc_productfields_field_product` fp
                    ON fp.`id_mzc_productfields_field` = f.`id_mzc_productfields_field`
                GROUP BY f.`id_mzc_productfields_field`
                ORDER BY f.`position` ASC, f.`id_mzc_productfields_field` DESC';

        return Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * All active SCOPE_PRODUCTS fields, regardless of which products they
     * currently include — backs the per-product "quick override" checkbox
     * list on the product edit page (Form\Type\ProductFieldsTabType).
     *
     * @return array<int,array> id_mzc_productfields_field, label
     */
    public static function getActiveProductScopedFields(int $idLang): array
    {
        $sql = 'SELECT f.`id_mzc_productfields_field`, fl.`label`
                FROM `' . _DB_PREFIX_ . 'mzc_productfields_field` f
                INNER JOIN `' . _DB_PREFIX_ . 'mzc_productfields_field_lang` fl
                    ON fl.`id_mzc_productfields_field` = f.`id_mzc_productfields_field`
                    AND fl.`id_lang` = ' . (int) $idLang . '
                WHERE f.`scope_type` = ' . self::SCOPE_PRODUCTS . ' AND f.`active` = 1
                ORDER BY f.`position` ASC, f.`id_mzc_productfields_field` ASC';

        return Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * Plain-text personalization values for an order, grouped by product line
     * — the textual counterpart to the JPG preview shown via
     * hookDisplayAdminOrderMain (see Mzcproductfields::hookDisplayAdminOrderMain's
     * docblock): a merchant fulfilling an engraving order needs to read the
     * exact submitted text reliably, not decipher it off a mockup photo.
     * Anvanto's own admin order block worked this way (plain field:value
     * rows), never as an image.
     *
     * Joins purely through our own `mzc_productfields_field_customization`
     * mapping table (not a generic `!is_module` filter) so this only ever
     * surfaces values for fields THIS module created — never another
     * module's own is_module=1 customizations sharing the same order.
     *
     * @return array<int,array{id_product:int,product_name:string,label:string,value:string}>
     */
    public static function getTextValuesForOrder(int $idOrder, int $idLang): array
    {
        if ($idOrder <= 0) {
            return [];
        }

        return self::getTextValuesByCartCondition(
            'INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.`id_cart` = c.`id_cart` WHERE o.`id_order` = ' . (int) $idOrder,
            $idLang
        );
    }

    /**
     * Same as getTextValuesForOrder(), but for a still-live cart — the
     * textual counterpart shown on the shopping cart page itself (see
     * Mzcproductfields::hookDisplayShoppingCartFooter's docblock): the
     * customer's own theme may or may not render core's native
     * "Product customization" modal for our is_module=1 fields, so this
     * gives every theme a guaranteed, theme-independent way to show it.
     *
     * @return array<int,array{id_product:int,product_name:string,label:string,value:string}>
     */
    public static function getTextValuesForCart(int $idCart, int $idLang): array
    {
        if ($idCart <= 0) {
            return [];
        }

        return self::getTextValuesByCartCondition('WHERE c.`id_cart` = ' . (int) $idCart, $idLang);
    }

    /**
     * @param string $cartJoinAndWhere e.g. "WHERE c.`id_cart` = 5", or a
     *                                  JOIN onto `orders` plus its own WHERE
     */
    private static function getTextValuesByCartCondition(string $cartJoinAndWhere, int $idLang): array
    {
        $sql = 'SELECT c.`id_product`, pl.`name` AS product_name, fl.`label`, cd.`value`
                FROM `' . _DB_PREFIX_ . 'customization` c
                INNER JOIN `' . _DB_PREFIX_ . 'customized_data` cd ON cd.`id_customization` = c.`id_customization`
                    AND cd.`type` = ' . (int) Product::CUSTOMIZE_TEXTFIELD . '
                INNER JOIN `' . _DB_PREFIX_ . 'mzc_productfields_field_customization` fc
                    ON fc.`id_customization_field` = cd.`index`
                INNER JOIN `' . _DB_PREFIX_ . 'mzc_productfields_field_lang` fl
                    ON fl.`id_mzc_productfields_field` = fc.`id_mzc_productfields_field`
                    AND fl.`id_lang` = ' . (int) $idLang . '
                INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                    ON pl.`id_product` = c.`id_product` AND pl.`id_lang` = ' . (int) $idLang . '
                ' . $cartJoinAndWhere . " AND cd.`value` != ''
                ORDER BY c.`id_product` ASC, fc.`id_mzc_productfields_field` ASC";

        return Db::getInstance()->executeS($sql) ?: [];
    }
}
