<?php
/**
 * MZC Carrier Attribute — Database installer (v2 — compound rules)
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
     * Create the compound-rule tables (rule_group / rule_condition / rule_carrier).
     * Migrates data from the legacy single-attribute table when present.
     *
     * @return bool
     */
    public function installDb()
    {
        $db = Db::getInstance();

        $newTableExists = (bool) $db->executeS(
            'SHOW TABLES LIKE \'' . _DB_PREFIX_ . 'mzc_carrier_attr_rule_group\''
        );

        if ($newTableExists) {
            return true;
        }

        $engine = defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'InnoDB';

        $success = $db->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mzc_carrier_attr_rule_group` (
                `id_rule_group` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT(10) UNSIGNED NOT NULL DEFAULT 1,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_rule_group`),
                KEY `idx_shop` (`id_shop`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;'
        );

        $success = $success && $db->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mzc_carrier_attr_rule_condition` (
                `id_rule_group` INT(10) UNSIGNED NOT NULL,
                `id_attribute` INT(10) UNSIGNED NOT NULL,
                PRIMARY KEY (`id_rule_group`, `id_attribute`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;'
        );

        $success = $success && $db->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mzc_carrier_attr_rule_carrier` (
                `id_rule_group` INT(10) UNSIGNED NOT NULL,
                `id_carrier_reference` INT(10) UNSIGNED NOT NULL,
                PRIMARY KEY (`id_rule_group`, `id_carrier_reference`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;'
        );

        if ($success) {
            $this->migrateOldRules();
        }

        return $success;
    }

    /**
     * Migrate legacy single-attribute rules into compound rule groups.
     * Each old id_attribute set becomes a one-condition rule group.
     */
    private function migrateOldRules()
    {
        $db = Db::getInstance();

        $oldTableExists = (bool) $db->executeS(
            'SHOW TABLES LIKE \'' . _DB_PREFIX_ . 'mzc_carrier_attr_rule\''
        );

        if (!$oldTableExists) {
            return;
        }

        $oldRows = $db->executeS(
            'SELECT `id_attribute`, `id_carrier_reference`
             FROM `' . _DB_PREFIX_ . 'mzc_carrier_attr_rule`'
        );

        if (!empty($oldRows) && is_array($oldRows)) {
            $grouped = [];
            foreach ($oldRows as $row) {
                $grouped[(int) $row['id_attribute']][] = (int) $row['id_carrier_reference'];
            }

            $now = date('Y-m-d H:i:s');
            $idShop = (int) Context::getContext()->shop->id;

            foreach ($grouped as $idAttr => $refs) {
                $db->insert('mzc_carrier_attr_rule_group', [
                    'id_shop' => $idShop,
                    'date_add' => $now,
                    'date_upd' => $now,
                ]);
                $idGroup = (int) $db->Insert_ID();

                $db->insert('mzc_carrier_attr_rule_condition', [
                    'id_rule_group' => $idGroup,
                    'id_attribute' => (int) $idAttr,
                ]);

                foreach ($refs as $ref) {
                    $db->insert('mzc_carrier_attr_rule_carrier', [
                        'id_rule_group' => $idGroup,
                        'id_carrier_reference' => (int) $ref,
                    ]);
                }
            }
        }

        $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'mzc_carrier_attr_rule`');
    }

    /**
     * Drop all module tables (both new and legacy)
     *
     * @return bool
     */
    public function uninstallDb()
    {
        $db = Db::getInstance();

        return $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'mzc_carrier_attr_rule_carrier`')
            && $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'mzc_carrier_attr_rule_condition`')
            && $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'mzc_carrier_attr_rule_group`')
            && $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'mzc_carrier_attr_rule`');
    }
}
