<?php
/**
 * MZC Product Fields — registers the new `displayShoppingCartFooter` hook
 * (see Mzcproductfields::hookDisplayShoppingCartFooter's docblock: a
 * theme-independent plain-text summary of personalization values on the
 * cart page). `registerHook()` is safe to call again on an install that
 * already has it — it's checked/no-ops rather than duplicating the row.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_7(Mzcproductfields $module): bool
{
    return $module->registerHook('displayShoppingCartFooter');
}
