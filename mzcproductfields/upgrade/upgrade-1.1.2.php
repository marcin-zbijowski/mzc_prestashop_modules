<?php
/**
 * MZC Product Fields — adds a per-rule `pos_y` override to
 * `mzc_productfields_background_attribute`, alongside the existing
 * `font`/`font_size` overrides added in upgrade-1.0.6.php (see
 * Form/Type/BackgroundRuleType's docblock). Existing rows keep it NULL,
 * meaning "no override — use the field's own preview_pos_y", so no backfill
 * is needed for previously-created rules.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_2(Mzcproductfields $module): bool
{
    $prefix = _DB_PREFIX_;
    $db = Db::getInstance();

    if (!columnExists($db, $prefix, 'mzc_productfields_background_attribute', 'pos_y')) {
        if (!$db->execute(
            'ALTER TABLE `' . $prefix . 'mzc_productfields_background_attribute`
             ADD COLUMN `pos_y` DECIMAL(5,2) NULL AFTER `font_size`'
        )) {
            return false;
        }
    }

    return true;
}

if (!function_exists('columnExists')) {
    // Guarded: an upgrade spanning multiple versions in one request
    // require_once's every upgrade-*.php file in sequence, so this helper
    // must not be declared unconditionally in more than one of them.
    function columnExists(Db $db, string $prefix, string $table, string $column): bool
    {
        return (bool) $db->executeS(
            'SHOW COLUMNS FROM `' . $prefix . $table . '` LIKE "' . pSQL($column) . '"'
        );
    }
}
