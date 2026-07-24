<?php
/**
 * MZC Product Fields — data cleanup for a real, shipped bug in
 * CombinationBackgroundResolver::saveRule(): it called Db::insert() without
 * passing $null_values = true, so a PHP null for font/font_size/pos_y
 * (i.e. "no override — use the field's own default", left blank on the
 * admin form) got interpolated as an empty string rather than the SQL
 * keyword NULL, which MySQL then silently coerced to 0 for the DECIMAL
 * columns. That code path is fixed in this same release (see
 * CombinationBackgroundResolver::saveRule()'s docblock) — this migration
 * repairs any rows already corrupted by it before the fix existed.
 *
 * Safe to blanket-convert 0 back to NULL here: a real font-size override of
 * literally 0% would render invisible text (no legitimate reason for a
 * merchant to ever set that), and `pos_y` is a column this same project
 * introduced only in the immediately preceding release, so no row could
 * have a genuine, intentionally-set 0 for it yet — every 0 there is this
 * bug's artifact, not real merchant data.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_4(Mzcproductfields $module): bool
{
    $prefix = _DB_PREFIX_;
    $db = Db::getInstance();

    return $db->execute('UPDATE `' . $prefix . 'mzc_productfields_background_attribute` SET `font` = NULL WHERE `font` = \'\'')
        && $db->execute('UPDATE `' . $prefix . 'mzc_productfields_background_attribute` SET `font_size` = NULL WHERE `font_size` = 0')
        && $db->execute('UPDATE `' . $prefix . 'mzc_productfields_background_attribute` SET `pos_y` = NULL WHERE `pos_y` = 0');
}
