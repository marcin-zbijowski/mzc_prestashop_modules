<?php
/**
 * 1.0.0 -> 1.1.0: Cloudflare Turnstile i ochrona formularza kontaktowego.
 *
 * Rejestruje hook formularza kontaktowego i dopisuje nowe ustawienia (istniejących
 * nie rusza). Turnstile zostaje nieaktywny, dopóki nie podasz kluczy.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_0($module)
{
    return $module->registerHook('actionFrontControllerInitAfter')
        && $module->installDefaults();
}
