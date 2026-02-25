<?php
/**
 * MZC Carrier Attribute — Rules repository (CRUD + cache)
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class MzcCarrierAttrRuleRepository
{
    /** @var array|null Per-request rules cache */
    private static $rulesCache = null;

    /** @var array Per-request carrier reference → id_carrier cache */
    private static $carrierRefCache = [];

    /**
     * Get all rules as [id_attribute => [id_carrier_reference, ...]]
     *
     * @return array
     */
    public function getRules()
    {
        if (self::$rulesCache !== null) {
            return self::$rulesCache;
        }

        $cached = Configuration::get('MZC_CARRIER_ATTR_RULES');
        if (!empty($cached)) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                self::$rulesCache = $decoded;

                return self::$rulesCache;
            }
        }

        self::$rulesCache = $this->loadFromDb();
        $this->saveToConfigCache(self::$rulesCache);

        return self::$rulesCache;
    }

    /**
     * Load rules from the database
     *
     * @return array
     */
    public function loadFromDb()
    {
        $rows = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT `id_attribute`, `id_carrier_reference`
             FROM `' . _DB_PREFIX_ . 'mzc_carrier_attr_rule`'
        );

        $rules = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $rules[(int) $row['id_attribute']][] = (int) $row['id_carrier_reference'];
            }
        }

        return $rules;
    }

    /**
     * Replace all rules with a new set
     *
     * @param array $newRules [id_attribute => [id_carrier_reference, ...]]
     *
     * @return bool
     */
    public function saveRules(array $newRules)
    {
        $db = Db::getInstance();
        $now = date('Y-m-d H:i:s');

        $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'mzc_carrier_attr_rule`');

        foreach ($newRules as $idAttr => $refs) {
            foreach ($refs as $ref) {
                $db->insert('mzc_carrier_attr_rule', [
                    'id_attribute' => (int) $idAttr,
                    'id_carrier_reference' => (int) $ref,
                    'date_add' => $now,
                    'date_upd' => $now,
                ]);
            }
        }

        $this->saveToConfigCache($newRules);
        $this->invalidate();

        return true;
    }

    /**
     * Serialize rules into Configuration for fast read access
     *
     * @param array $rules
     */
    public function saveToConfigCache(array $rules)
    {
        Configuration::updateValue('MZC_CARRIER_ATTR_RULES', json_encode($rules));
    }

    /**
     * Invalidate all per-request caches
     */
    public function invalidate()
    {
        self::$rulesCache = null;
        self::$carrierRefCache = [];
    }

    /**
     * Resolve id_carrier_reference → current active id_carrier
     *
     * @param int $idReference
     *
     * @return int|false
     */
    public function resolveCarrierReference($idReference)
    {
        if (isset(self::$carrierRefCache[$idReference])) {
            return self::$carrierRefCache[$idReference];
        }

        $carrier = Carrier::getCarrierByReference($idReference);
        if (Validate::isLoadedObject($carrier) && $carrier->active && !$carrier->deleted) {
            self::$carrierRefCache[$idReference] = (int) $carrier->id;
        } else {
            self::$carrierRefCache[$idReference] = false;
        }

        return self::$carrierRefCache[$idReference];
    }

    /**
     * Get attribute IDs for given combination IDs (batch query)
     *
     * @param int[] $combinationIds
     *
     * @return array [id_product_attribute => [id_attribute, ...]]
     */
    public function getCombinationAttributes(array $combinationIds)
    {
        if (empty($combinationIds)) {
            return [];
        }

        $ids = implode(',', array_map('intval', $combinationIds));

        $rows = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT pac.`id_product_attribute`, pac.`id_attribute`
             FROM `' . _DB_PREFIX_ . 'product_attribute_combination` pac
             WHERE pac.`id_product_attribute` IN (' . $ids . ')'
        );

        $map = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $map[(int) $row['id_product_attribute']][] = (int) $row['id_attribute'];
            }
        }

        return $map;
    }

    /**
     * Get a list of all attribute IDs in the shop
     *
     * @return int[]
     */
    public function getAllAttributeIds()
    {
        $rows = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT `id_attribute` FROM `' . _DB_PREFIX_ . 'attribute`'
        );

        $ids = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $ids[] = (int) $row['id_attribute'];
            }
        }

        return $ids;
    }
}
