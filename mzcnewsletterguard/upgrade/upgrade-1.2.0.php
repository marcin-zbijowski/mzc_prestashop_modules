<?php
/**
 * 1.1.x -> 1.2.0: blokada fałszywych adresów przy zakładaniu konta.
 *
 * Rejestruje hook `actionSubmitAccountBefore` i dopisuje ustawienia (istniejących nie rusza).
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_2_0($module)
{
    return $module->registerHook('actionSubmitAccountBefore')
        && $module->installDefaults();
}
