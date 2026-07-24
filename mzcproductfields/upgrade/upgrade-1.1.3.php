<?php
/**
 * MZC Product Fields — widens PrestaShop's own NATIVE `customized_data.value`
 * column from VARCHAR(255) (its shipped width on PS 8.2.7) to TEXT.
 *
 * Confirmed root cause of a real production report: a long engraved
 * personalization text showed the full content in this module's own
 * JPG snapshot (captured client-side, never touches this column) but was cut
 * off mid-sentence in the "Wartości personalizacji" text panel — because
 * that panel reads `customized_data.value` directly, and MySQL silently
 * truncates any INSERT past 255 characters there (PrestaShop's own DB layer
 * disables strict SQL mode per-connection — see
 * classes/db/DbMySQLi.php / DbPDO.php's `SET SESSION sql_mode = ''` — so no
 * error is raised, it just quietly loses the tail of the text). Confirmed
 * identical on real production and this project's own PS 8.2.7 docker;
 * PS 9.x already ships this same core column as VARCHAR(1024), so this
 * widening only brings PS 8.2.7 in line with what upstream itself later
 * shipped — and going to TEXT rather than a fixed length removes the limit
 * outright for arbitrarily long personalizations (poems, engravings).
 *
 * This is NOT one of this module's own tables — it's PrestaShop's own
 * `customized_data`, used store-wide by core's native cart/order
 * customization display too (cart modal, order confirmation, invoices,
 * emails all read this same column) — so this single widening fixes the
 * truncation everywhere at once, not just in this module's own admin panel.
 * Purely additive (a widening ALTER, no data loss) and guarded to be a
 * no-op if the column has already been widened past VARCHAR.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_3(Mzcproductfields $module): bool
{
    $prefix = _DB_PREFIX_;
    $db = Db::getInstance();

    $column = $db->getRow(
        'SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = "' . pSQL($prefix . 'customized_data') . '"
         AND COLUMN_NAME = "value"'
    );

    if ($column && strtolower((string) $column['DATA_TYPE']) === 'varchar') {
        if (!$db->execute('ALTER TABLE `' . $prefix . 'customized_data` MODIFY `value` TEXT NOT NULL')) {
            return false;
        }
    }

    return true;
}
