<?php
/**
 * MZC Carrier Attribute — Rules repository (v2 — grouped OR/AND rules)
 *
 * Conditions are stored grouped by attribute group:
 *   ['conditions' => [ groupId => [attrId, ...], ... ], 'carriers' => [...]]
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
    /** @var array|null Per-request rule groups cache */
    private static $rulesCache;

    /** @var array Per-request carrier reference → id_carrier cache */
    private static $carrierRefCache = [];

    /**
     * Get all rule groups.
     *
     * Each group: ['conditions' => [groupId => [attrId, ...]], 'carriers' => [id_carrier_reference, ...]]
     *
     * @return array
     */
    public function getRuleGroups()
    {
        if (self::$rulesCache !== null) {
            return self::$rulesCache;
        }

        $cached = Configuration::get('MZC_CARRIER_ATTR_RULES');
        if (!empty($cached)) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded) && isset($decoded['v']) && $decoded['v'] === 2) {
                self::$rulesCache = $decoded['groups'];

                return self::$rulesCache;
            }
        }

        self::$rulesCache = $this->loadFromDb();
        $this->saveToConfigCache(self::$rulesCache);

        return self::$rulesCache;
    }

    /**
     * Load rule groups from the three normalised tables.
     *
     * Conditions are grouped by their id_attribute_group via a JOIN on
     * ps_attribute so the matcher can apply OR-within / AND-between logic.
     *
     * @return array
     */
    public function loadFromDb()
    {
        $db = Db::getInstance(_PS_USE_SQL_SLAVE_);
        $idShop = (int) Context::getContext()->shop->id;

        $groups = $db->executeS(
            'SELECT `id_rule_group`
             FROM `' . _DB_PREFIX_ . 'mzc_carrier_attr_rule_group`
             WHERE `id_shop` = ' . $idShop .
            ' ORDER BY `id_rule_group` ASC'
        );

        if (empty($groups) || !is_array($groups)) {
            return [];
        }

        $groupIds = array_map(function ($r) {
            return (int) $r['id_rule_group'];
        }, $groups);
        $idsList = implode(',', $groupIds);

        /* JOIN ps_attribute to get id_attribute_group for grouping */
        $condRows = $db->executeS(
            'SELECT rc.`id_rule_group`, rc.`id_attribute`, a.`id_attribute_group`
             FROM `' . _DB_PREFIX_ . 'mzc_carrier_attr_rule_condition` rc
             INNER JOIN `' . _DB_PREFIX_ . 'attribute` a
               ON a.`id_attribute` = rc.`id_attribute`
             WHERE rc.`id_rule_group` IN (' . $idsList . ')'
        );

        $carrierRows = $db->executeS(
            'SELECT `id_rule_group`, `id_carrier_reference`
             FROM `' . _DB_PREFIX_ . 'mzc_carrier_attr_rule_carrier`
             WHERE `id_rule_group` IN (' . $idsList . ')'
        );

        /* Build grouped conditions: ruleGroup → attrGroup → [attrIds] */
        $conditions = [];
        if (is_array($condRows)) {
            foreach ($condRows as $row) {
                $rg = (int) $row['id_rule_group'];
                $ag = (int) $row['id_attribute_group'];
                $conditions[$rg][$ag][] = (int) $row['id_attribute'];
            }
        }

        $carriers = [];
        if (is_array($carrierRows)) {
            foreach ($carrierRows as $row) {
                $carriers[(int) $row['id_rule_group']][] = (int) $row['id_carrier_reference'];
            }
        }

        $ruleGroups = [];
        foreach ($groupIds as $gid) {
            if (!empty($conditions[$gid]) && !empty($carriers[$gid])) {
                /* Sort each group's attr ids for consistency */
                $conds = $conditions[$gid];
                foreach ($conds as &$ids) {
                    sort($ids);
                }
                unset($ids);
                ksort($conds);

                $ruleGroups[] = [
                    'conditions' => $conds,
                    'carriers' => $carriers[$gid],
                ];
            }
        }

        return $ruleGroups;
    }

    /**
     * Replace all rule groups for the current shop.
     *
     * @param array $ruleGroups [['conditions' => [groupId => [attrId, ...]], 'carriers' => [...]], ...]
     *
     * @return bool
     */
    public function saveRuleGroups(array $ruleGroups)
    {
        $db = Db::getInstance();
        $idShop = (int) Context::getContext()->shop->id;
        $now = date('Y-m-d H:i:s');

        /* delete existing groups for this shop */
        $existing = $db->executeS(
            'SELECT `id_rule_group`
             FROM `' . _DB_PREFIX_ . 'mzc_carrier_attr_rule_group`
             WHERE `id_shop` = ' . $idShop
        );

        if (!empty($existing) && is_array($existing)) {
            $ids = implode(',', array_map(function ($r) {
                return (int) $r['id_rule_group'];
            }, $existing));

            $db->execute(
                'DELETE FROM `' . _DB_PREFIX_ . 'mzc_carrier_attr_rule_carrier`
                 WHERE `id_rule_group` IN (' . $ids . ')'
            );
            $db->execute(
                'DELETE FROM `' . _DB_PREFIX_ . 'mzc_carrier_attr_rule_condition`
                 WHERE `id_rule_group` IN (' . $ids . ')'
            );
            $db->execute(
                'DELETE FROM `' . _DB_PREFIX_ . 'mzc_carrier_attr_rule_group`
                 WHERE `id_shop` = ' . $idShop
            );
        }

        /* insert new groups */
        foreach ($ruleGroups as $group) {
            if (empty($group['conditions']) || empty($group['carriers'])) {
                continue;
            }

            $db->insert('mzc_carrier_attr_rule_group', [
                'id_shop' => $idShop,
                'date_add' => $now,
                'date_upd' => $now,
            ]);
            $idGroup = (int) $db->Insert_ID();

            /* Flatten grouped conditions for DB storage */
            foreach ($group['conditions'] as $attrIds) {
                foreach ($attrIds as $idAttr) {
                    $db->insert('mzc_carrier_attr_rule_condition', [
                        'id_rule_group' => $idGroup,
                        'id_attribute' => (int) $idAttr,
                    ]);
                }
            }

            foreach ($group['carriers'] as $ref) {
                $db->insert('mzc_carrier_attr_rule_carrier', [
                    'id_rule_group' => $idGroup,
                    'id_carrier_reference' => (int) $ref,
                ]);
            }
        }

        $this->saveToConfigCache($ruleGroups);
        $this->invalidate();

        return true;
    }

    /**
     * Persist rules as JSON in Configuration for fast reads.
     *
     * @param array $ruleGroups
     */
    public function saveToConfigCache(array $ruleGroups)
    {
        $stripped = [];
        foreach ($ruleGroups as $g) {
            /* Convert grouped conditions to string-keyed for JSON */
            $conds = [];
            foreach ($g['conditions'] as $gid => $ids) {
                $conds[(string) $gid] = array_values($ids);
            }
            $stripped[] = [
                'conditions' => $conds,
                'carriers' => $g['carriers'],
            ];
        }

        Configuration::updateValue('MZC_CARRIER_ATTR_RULES', json_encode([
            'v' => 2,
            'groups' => $stripped,
        ]));
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
}
