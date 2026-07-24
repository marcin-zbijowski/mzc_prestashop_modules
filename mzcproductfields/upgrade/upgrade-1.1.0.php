<?php
/**
 * MZC Product Fields — registers the new `displayOrderConfirmation` hook
 * (see Mzcproductfields::hookDisplayOrderConfirmation's docblock: a
 * theme-independent plain-text summary of personalization values on the
 * order confirmation / "thank you" page). `registerHook()` is safe to call
 * again on an install that already has it.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_0(Mzcproductfields $module): bool
{
    return $module->registerHook('displayOrderConfirmation');
}
