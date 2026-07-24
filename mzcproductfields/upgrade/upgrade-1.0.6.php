<?php
/**
 * MZC Product Fields — adds per-rule `font`/`font_size` overrides to
 * `mzc_productfields_background_attribute` (see
 * Service/CombinationBackgroundResolver's docblock). Existing rows keep both
 * columns NULL, meaning "no override — use the field's own preview_font /
 * preview_font_size", so no backfill is needed for previously-created rules.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_6(Mzcproductfields $module): bool
{
    $prefix = _DB_PREFIX_;
    $db = Db::getInstance();

    if (!columnExists($db, $prefix, 'mzc_productfields_background_attribute', 'font')) {
        if (!$db->execute(
            'ALTER TABLE `' . $prefix . 'mzc_productfields_background_attribute`
             ADD COLUMN `font` VARCHAR(100) NULL AFTER `id_background`'
        )) {
            return false;
        }
    }

    if (!columnExists($db, $prefix, 'mzc_productfields_background_attribute', 'font_size')) {
        if (!$db->execute(
            'ALTER TABLE `' . $prefix . 'mzc_productfields_background_attribute`
             ADD COLUMN `font_size` DECIMAL(5,2) NULL AFTER `font`'
        )) {
            return false;
        }
    }

    return true;
}

if (!function_exists('columnExists')) {
    // Guarded: an upgrade spanning multiple versions in one request (e.g.
    // 1.0.4 -> 1.0.6) require_once's every upgrade-*.php file in sequence, so
    // this helper must not be declared unconditionally in more than one of
    // them.
    function columnExists(Db $db, string $prefix, string $table, string $column): bool
    {
        return (bool) $db->executeS(
            'SHOW COLUMNS FROM `' . $prefix . $table . '` LIKE "' . pSQL($column) . '"'
        );
    }
}
