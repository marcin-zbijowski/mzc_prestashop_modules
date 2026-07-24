<?php
/**
 * MZC Product Fields — schema catch-up for everything added during
 * development that shipped as plain code changes without an upgrade script
 * (preview_max_width, is_front_field, the order-snapshot table). Confirmed as
 * a real problem: a fresh code deploy updates the admin form/front-office
 * behavior immediately, but MzcProductFieldsInstaller::installDb() only ever
 * runs at first install — an existing installation's database never picks up
 * new columns/tables on its own, so saving a field failed with
 * "Unknown column 'is_front_field'" once the deployed code expected it.
 *
 * Every statement here is written to be safe to run again (checks for
 * existence first / CREATE TABLE IF NOT EXISTS), so this module's own version
 * bumps don't have to line up exactly with when each of these was originally
 * introduced, and re-running this upgrade (e.g. after a partial manual fix)
 * is harmless.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_5(Mzcproductfields $module): bool
{
    $prefix = _DB_PREFIX_;
    $engine = defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'InnoDB';
    $db = Db::getInstance();

    if (!columnExists($db, $prefix, 'mzc_productfields_field', 'preview_max_width')) {
        if (!$db->execute(
            'ALTER TABLE `' . $prefix . 'mzc_productfields_field`
             ADD COLUMN `preview_max_width` DECIMAL(5,2) NULL AFTER `preview_font_size`'
        )) {
            return false;
        }
    }

    if (!columnExists($db, $prefix, 'mzc_productfields_field', 'is_front_field')) {
        if (!$db->execute(
            'ALTER TABLE `' . $prefix . 'mzc_productfields_field`
             ADD COLUMN `is_front_field` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER `id_background`'
        )) {
            return false;
        }

        // A field already configured with its own static background was
        // clearly never meant to float on the product's own photo — flip it
        // to non-front so its existing behaviour is preserved exactly.
        // Fields that instead rely PURELY on a combination rule (no static
        // id_background of their own) can't be inferred this way and default
        // to Front — those need a manual review per field, same as on the
        // dev/test environments this was first built against.
        $db->execute(
            'UPDATE `' . $prefix . 'mzc_productfields_field`
             SET `is_front_field` = 0
             WHERE `id_background` IS NOT NULL'
        );
    }

    if (!$db->execute(
        'CREATE TABLE IF NOT EXISTS `' . $prefix . 'mzc_productfields_order_snapshot` (
            `id_order_snapshot` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_customization` INT(10) UNSIGNED NOT NULL,
            `filename` VARCHAR(255) NOT NULL,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_order_snapshot`),
            KEY `idx_customization` (`id_customization`)
        ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;'
    )) {
        return false;
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
