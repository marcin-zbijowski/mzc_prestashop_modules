<?php
/**
 * MZC Carrier Attribute — Database installer
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class MzcCarrierAttrInstaller
{
    /**
     * Create the rules table
     *
     * @return bool
     */
    public function installDb()
    {
        $tableExists = (bool) Db::getInstance()->executeS(
            'SHOW TABLES LIKE \'' . _DB_PREFIX_ . 'mzc_carrier_attr_rule\''
        );

        if ($tableExists) {
            return true;
        }

        $engine = defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'InnoDB';

        return Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mzc_carrier_attr_rule` (
                `id_rule` int(11) NOT NULL AUTO_INCREMENT,
                `id_attribute` int(11) NOT NULL,
                `id_carrier_reference` int(11) NOT NULL,
                `date_add` datetime NOT NULL,
                `date_upd` datetime NOT NULL,
                PRIMARY KEY (`id_rule`),
                KEY `idx_attribute` (`id_attribute`),
                KEY `idx_carrier_ref` (`id_carrier_reference`),
                UNIQUE KEY `uk_attr_carrier` (`id_attribute`, `id_carrier_reference`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;'
        );
    }

    /**
     * Drop the rules table
     *
     * @return bool
     */
    public function uninstallDb()
    {
        return Db::getInstance()->execute(
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'mzc_carrier_attr_rule`'
        );
    }
}
