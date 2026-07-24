<?php
/**
 * MZC Product Fields — schema installer.
 *
 * `mzc_productfields_order_archive`(+`_lang`) is deliberately excluded from
 * uninstallDb() — it must survive this module's own uninstall, since it holds
 * a permanent copy of historical Anvanto order-personalization data that
 * Anvanto's own uninstall would otherwise destroy (see
 * classes/MzcProductFieldsOrderArchive.php).
 *
 * All price columns are DECIMAL(20,6) — matching PrestaShop's native
 * `product.price` precision — per the owner's explicit requirement to avoid
 * Anvanto's decimal(12,2) rounding artifacts.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class MzcProductFieldsInstaller
{
    public function installDb(): bool
    {
        $engine = defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'InnoDB';
        $prefix = _DB_PREFIX_;

        $queries = [];

        $queries[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'mzc_productfields_field` (
            `id_mzc_productfields_field` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `scope_type` TINYINT(1) UNSIGNED NOT NULL DEFAULT 2,
            `scope_id` INT(10) UNSIGNED NULL,
            `field_type` VARCHAR(20) NOT NULL DEFAULT "text",
            `required` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            `is_paid` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            `price_type` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            `price_amount` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
            `id_tax_rules_group` INT(10) UNSIGNED NULL,
            `max_length` INT(10) UNSIGNED NULL,
            `char_counter` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            `position` INT(10) UNSIGNED NOT NULL DEFAULT 0,
            `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            `preview_pos_x` DECIMAL(5,2) NULL,
            `preview_pos_y` DECIMAL(5,2) NULL,
            `preview_font` VARCHAR(100) NULL,
            `preview_font_size` DECIMAL(5,2) NULL,
            `preview_max_width` DECIMAL(5,2) NULL,
            `preview_color` VARCHAR(7) NULL,
            `id_background` INT(10) UNSIGNED NULL,
            `is_front_field` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            `source_an_field_id` INT(10) UNSIGNED NULL,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_mzc_productfields_field`),
            KEY `idx_scope` (`scope_type`, `scope_id`),
            KEY `idx_active` (`active`),
            KEY `idx_background` (`id_background`),
            KEY `idx_source_an_field_id` (`source_an_field_id`)
        ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;';

        $queries[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'mzc_productfields_field_lang` (
            `id_mzc_productfields_field` INT(10) UNSIGNED NOT NULL,
            `id_lang` INT(10) UNSIGNED NOT NULL,
            `label` VARCHAR(255) NOT NULL,
            `placeholder` VARCHAR(255) NULL,
            PRIMARY KEY (`id_mzc_productfields_field`, `id_lang`)
        ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;';

        $queries[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'mzc_productfields_field_value` (
            `id_mzc_productfields_field_value` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_mzc_productfields_field` INT(10) UNSIGNED NOT NULL,
            `price_amount` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
            `id_tax_rules_group` INT(10) UNSIGNED NULL,
            `position` INT(10) UNSIGNED NOT NULL DEFAULT 0,
            `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (`id_mzc_productfields_field_value`),
            KEY `idx_field` (`id_mzc_productfields_field`)
        ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;';

        $queries[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'mzc_productfields_field_value_lang` (
            `id_mzc_productfields_field_value` INT(10) UNSIGNED NOT NULL,
            `id_lang` INT(10) UNSIGNED NOT NULL,
            `label` VARCHAR(255) NOT NULL,
            PRIMARY KEY (`id_mzc_productfields_field_value`, `id_lang`)
        ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;';

        $queries[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'mzc_productfields_field_product` (
            `id_mzc_productfields_field` INT(10) UNSIGNED NOT NULL,
            `id_product` INT(10) UNSIGNED NOT NULL,
            PRIMARY KEY (`id_mzc_productfields_field`, `id_product`),
            KEY `idx_product` (`id_product`)
        ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;';

        // Maps one of our fields (for a given product) to the native
        // `customization_field` row we lazily create for it — see
        // Service/CustomizationFieldSynchronizer. `customization_field` has no
        // natural FK back to us, so this mapping must be persisted durably:
        // once created, id_customization_field is baked into carts/orders
        // already placed and can never change for that (field, product) pair.
        $queries[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'mzc_productfields_field_customization` (
            `id_mzc_productfields_field` INT(10) UNSIGNED NOT NULL,
            `id_product` INT(10) UNSIGNED NOT NULL,
            `id_customization_field` INT(10) UNSIGNED NOT NULL,
            PRIMARY KEY (`id_mzc_productfields_field`, `id_product`),
            UNIQUE KEY `idx_customization_field` (`id_customization_field`)
        ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;';

        $queries[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'mzc_productfields_font` (
            `id_font` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `font_family` VARCHAR(50) NOT NULL,
            `filename` VARCHAR(255) NOT NULL,
            `original_filename` VARCHAR(255) NOT NULL,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_font`),
            UNIQUE KEY `idx_font_family` (`font_family`)
        ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;';

        // One uploaded image per "side" of a product (e.g. front/back) that a
        // paid-or-free field's live preview can be anchored to instead of the
        // theme's own product photo — see Service/ProductFieldsPresenter and
        // the front-office template, which render a dedicated preview box per
        // distinct background rather than trying to overlay onto whichever
        // photo the theme happens to show.
        $queries[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'mzc_productfields_background` (
            `id_background` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `filename` VARCHAR(255) NOT NULL,
            `original_filename` VARCHAR(255) NOT NULL,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_background`)
        ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;';

        // Overrides the background image used for the live preview based on
        // the product's CURRENTLY SELECTED combination — see
        // Service/CombinationBackgroundResolver's docblock. GLOBAL, not
        // per-product: one row = one admin-configured rule: "if the customer
        // has selected all of these attribute values (anywhere in the
        // catalog), use this background". `attribute_ids` is a sorted,
        // comma-separated list of `attribute`.`id_attribute` values — a
        // single id for a rule like "just 21x30cm" (regardless of any other
        // attribute group), or several for a rule like "Canvas AND 30x40cm"
        // specifically. Resolution prefers the rule with the MOST attribute
        // ids that are all currently selected (most specific wins).
        $queries[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'mzc_productfields_background_attribute` (
            `id_background_attribute` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `attribute_ids` VARCHAR(255) NOT NULL,
            `id_background` INT(10) UNSIGNED NOT NULL,
            `font` VARCHAR(100) NULL,
            `font_size` DECIMAL(5,2) NULL,
            `pos_y` DECIMAL(5,2) NULL,
            PRIMARY KEY (`id_background_attribute`),
            UNIQUE KEY `idx_attribute_ids` (`attribute_ids`),
            KEY `idx_background` (`id_background`)
        ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;';

        $queries[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'mzc_productfields_order_archive` (
            `id_mzc_productfields_order_archive` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `source_id_field` INT(10) UNSIGNED NULL,
            `source_pf_order_id` INT(10) UNSIGNED NULL,
            `source` VARCHAR(30) NOT NULL DEFAULT "an_productfields",
            `id_cart` INT(10) UNSIGNED NOT NULL,
            `id_order` INT(10) UNSIGNED NOT NULL,
            `id_order_detail` INT(10) UNSIGNED NOT NULL,
            `id_product` INT(10) UNSIGNED NOT NULL,
            `id_product_attribute` INT(10) UNSIGNED NOT NULL DEFAULT 0,
            `id_customization` INT(10) UNSIGNED NOT NULL DEFAULT 0,
            `qty` INT(10) UNSIGNED NOT NULL DEFAULT 1,
            `price` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
            `price_wt` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_mzc_productfields_order_archive`),
            KEY `idx_order` (`id_order`),
            KEY `idx_product` (`id_product`),
            KEY `idx_source_pf_order_id` (`source_pf_order_id`)
        ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;';

        $queries[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'mzc_productfields_order_archive_lang` (
            `id_mzc_productfields_order_archive` INT(10) UNSIGNED NOT NULL,
            `id_lang` INT(10) UNSIGNED NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `title_value` TEXT NOT NULL,
            PRIMARY KEY (`id_mzc_productfields_order_archive`, `id_lang`)
        ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;';

        // A JPG snapshot of "what the customer saw" — the live preview
        // composited (background + typed text, same position/font/color data)
        // via Canvas at content-confirmation time, so the backoffice order
        // page can show exactly what was personalized without the merchant
        // having to reopen the product with the same combination selected.
        // Deliberately NOT dropped in uninstallDb(), same reasoning as
        // mzc_productfields_order_archive above: these are historical records
        // of REAL placed orders, which must outlive an unrelated later
        // uninstall of this config module. `id_customization` links back to
        // the native `customization` table (itself already permanent order
        // data), not to our own field-definition tables, so it's safe to keep
        // even after every mzc_productfields_field row is gone.
        $queries[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'mzc_productfields_order_snapshot` (
            `id_order_snapshot` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_customization` INT(10) UNSIGNED NOT NULL,
            `filename` VARCHAR(255) NOT NULL,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_order_snapshot`),
            KEY `idx_customization` (`id_customization`)
        ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;';

        foreach ($queries as $sql) {
            if (!Db::getInstance()->execute($sql)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Drops only the field-definition tables. `mzc_productfields_order_archive`
     * and `_order_archive_lang` are intentionally NOT dropped here — see the
     * class docblock above.
     */
    public function uninstallDb(): bool
    {
        $prefix = _DB_PREFIX_;
        $tables = [
            'mzc_productfields_field_value_lang',
            'mzc_productfields_field_value',
            'mzc_productfields_field_customization',
            'mzc_productfields_field_product',
            'mzc_productfields_field_lang',
            'mzc_productfields_field',
            'mzc_productfields_font',
            'mzc_productfields_background_attribute',
            'mzc_productfields_background',
        ];

        foreach ($tables as $table) {
            if (!Db::getInstance()->execute('DROP TABLE IF EXISTS `' . $prefix . $table . '`')) {
                return false;
            }
        }

        return true;
    }
}
