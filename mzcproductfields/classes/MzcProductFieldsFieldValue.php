<?php
/**
 * MZC Product Fields — priced option ObjectModel (for select/radio/checkbox/multiselect).
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class MzcProductFieldsFieldValue extends ObjectModel
{
    /** @var int */
    public $id_mzc_productfields_field_value;

    /** @var int */
    public $id_mzc_productfields_field;

    /** @var string decimal(20,6) as string */
    public $price_amount;

    /** @var int|null overrides the parent field's id_tax_rules_group when set */
    public $id_tax_rules_group;

    /** @var int */
    public $position;

    /** @var int 0|1 */
    public $active;

    /** @var array<int,string> id_lang => label */
    public $label;

    public static $definition = [
        'table' => 'mzc_productfields_field_value',
        'primary' => 'id_mzc_productfields_field_value',
        'multilang' => true,
        'fields' => [
            'id_mzc_productfields_field' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'price_amount' => ['type' => self::TYPE_STRING, 'validate' => 'isPrice'],
            'id_tax_rules_group' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'allow_null' => true],
            'position' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'label' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'required' => true, 'size' => 255],
        ],
    ];

    /**
     * @return array<int,array> option rows for one field, in one language
     */
    public static function getValuesForField(int $idField, int $idLang, bool $activeOnly = true): array
    {
        if ($idField <= 0) {
            return [];
        }

        $sql = 'SELECT v.*, vl.`label`
                FROM `' . _DB_PREFIX_ . 'mzc_productfields_field_value` v
                INNER JOIN `' . _DB_PREFIX_ . 'mzc_productfields_field_value_lang` vl
                    ON vl.`id_mzc_productfields_field_value` = v.`id_mzc_productfields_field_value`
                    AND vl.`id_lang` = ' . (int) $idLang . '
                WHERE v.`id_mzc_productfields_field` = ' . (int) $idField
                . ($activeOnly ? ' AND v.`active` = 1' : '')
                . ' ORDER BY v.`position` ASC, v.`id_mzc_productfields_field_value` ASC';

        return Db::getInstance()->executeS($sql) ?: [];
    }
}
