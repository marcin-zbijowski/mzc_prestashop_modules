<?php
/**
 * 1.0.0 -> 1.1.0: Google Tag Manager loading.
 *
 * Existing installations only have displayHeader, displayOrderConfirmation and
 * displayBeforeBodyClosingTag registered. This adds the after-<body> hook for the
 * <noscript> iframe, seeds the two settings (empty and off, so an upgrade changes
 * nothing on the page until someone enters the container ID), and puts the module
 * below megacookies on displayHeader.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @param Mzcga4gtm $module
 */
function upgrade_module_1_1_0($module): bool
{
    if (Configuration::get(Mzcga4gtm::CONFIG_GTM_ID) === false) {
        Configuration::updateValue(Mzcga4gtm::CONFIG_GTM_ID, '');
    }

    if (Configuration::get(Mzcga4gtm::CONFIG_GTM_ENABLED) === false) {
        Configuration::updateValue(Mzcga4gtm::CONFIG_GTM_ENABLED, 0);
    }

    return $module->registerHook('displayAfterBodyOpeningTag')
        && $module->ensureHeaderOrder();
}
