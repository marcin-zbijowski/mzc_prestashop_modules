<?php
/**
 * MZC Product Fields — registers the new `displayCustomization` hook (see
 * Mzcproductfields::hookDisplayCustomization()'s docblock: fixes a real,
 * shipped crash on the admin order detail page — a TypeError constructing
 * `OrderProductCustomizationForViewing` with a null value — that only
 * started surfacing after 1.1.6/1.1.7 made our customizations genuinely
 * visible to core's own rendering paths for the first time).
 * `registerHook()` also creates the hook row itself if it doesn't already
 * exist in this shop's `hook` table (confirmed missing here, since no other
 * installed module happened to use it), which is required for
 * `Hook::exec('displayCustomization', ...)` to ever reach our module at all.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_8(Mzcproductfields $module): bool
{
    return $module->registerHook('displayCustomization');
}
