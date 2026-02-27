<?php
/**
 * MZC Carrier Attribute — Carrier filter (v2 — grouped OR/AND matching)
 *
 * Within an attribute group, checked values are OR'd.
 * Between attribute groups, the constraint is AND'd.
 * Unchecked groups impose no constraint.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class MzcCarrierAttrCarrierFilter
{
    /** @var MzcCarrierAttrRuleRepository */
    private $repository;

    /**
     * @param MzcCarrierAttrRuleRepository $repository
     */
    public function __construct(MzcCarrierAttrRuleRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Filter delivery options based on grouped attribute rules.
     *
     * For every product combination in the cart the module finds all rule
     * groups whose conditions match.  A rule matches when, for every
     * attribute group that has conditions, at least one condition value
     * is present in the combination's attributes (OR within group,
     * AND between groups).
     *
     * @param array $deliveryOptionList Passed by reference from the hook
     * @param Cart $cart
     */
    public function filterDeliveryOptions(array &$deliveryOptionList, Cart $cart)
    {
        if (empty($deliveryOptionList)) {
            return;
        }

        $products = $cart->getProducts();
        if (empty($products)) {
            return;
        }

        $combinationIds = [];
        $addrCombinations = [];

        foreach ($products as $product) {
            $idPa = (int) $product['id_product_attribute'];
            if ($idPa === 0) {
                continue;
            }
            $combinationIds[$idPa] = $idPa;
            $idAddr = (int) ($product['id_address_delivery'] ?? 0);
            $addrCombinations[$idAddr][$idPa] = $idPa;
        }

        if (empty($combinationIds)) {
            return;
        }

        $comboAttrMap = $this->repository->getCombinationAttributes(array_values($combinationIds));
        $ruleGroups = $this->repository->getRuleGroups();

        if (empty($ruleGroups)) {
            return;
        }

        foreach ($deliveryOptionList as $idAddress => &$options) {
            $combosForAddr = isset($addrCombinations[$idAddress])
                ? $addrCombinations[$idAddress]
                : $combinationIds;

            $allowedSets = [];

            foreach ($combosForAddr as $idPa) {
                if (!isset($comboAttrMap[$idPa])) {
                    continue;
                }
                $comboAttrs = $comboAttrMap[$idPa];

                foreach ($ruleGroups as $group) {
                    if ($this->ruleMatchesCombination($group['conditions'], $comboAttrs)) {
                        $allowedSets[] = $group['carriers'];
                    }
                }
            }

            if (empty($allowedSets)) {
                continue;
            }

            $allowedReferences = $allowedSets[0];
            for ($i = 1, $count = count($allowedSets); $i < $count; ++$i) {
                $allowedReferences = array_intersect($allowedReferences, $allowedSets[$i]);
            }

            if (empty($allowedReferences)) {
                $options = [];
                continue;
            }

            $allowedCarrierIds = [];
            foreach ($allowedReferences as $ref) {
                $cid = $this->repository->resolveCarrierReference($ref);
                if ($cid !== false) {
                    $allowedCarrierIds[$cid] = true;
                }
            }

            foreach ($options as $key => $option) {
                if (!isset($option['carrier_list'])) {
                    continue;
                }
                foreach (array_keys($option['carrier_list']) as $idCarrier) {
                    if (!isset($allowedCarrierIds[$idCarrier])) {
                        unset($options[$key]);
                        break;
                    }
                }
            }
        }
        unset($options);
    }

    /**
     * Check whether a rule matches a product combination.
     *
     * Conditions are grouped by attribute group:
     *   ['conditions' => [ groupId => [attrId, ...], ... ]]
     *
     * For each attribute group present in the conditions, at least one of its
     * values must appear in the combination's attributes (OR within group).
     * All groups with conditions must pass (AND between groups).
     *
     * @param array $conditions Grouped conditions: [groupId => [attrId, ...]]
     * @param int[] $comboAttrs Combination attribute IDs
     *
     * @return bool
     */
    private function ruleMatchesCombination(array $conditions, array $comboAttrs)
    {
        if (empty($conditions)) {
            return false;
        }

        foreach ($conditions as $attrIds) {
            $found = false;
            foreach ($attrIds as $attrId) {
                if (in_array($attrId, $comboAttrs, true)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a cart has any carrier restrictions based on grouped rules.
     *
     * @param Cart $cart
     *
     * @return bool
     */
    public function cartHasRestrictions(Cart $cart)
    {
        $ruleGroups = $this->repository->getRuleGroups();
        if (empty($ruleGroups)) {
            return false;
        }

        $products = $cart->getProducts();
        $combinationIds = [];

        foreach ($products as $product) {
            $idPa = (int) $product['id_product_attribute'];
            if ($idPa > 0) {
                $combinationIds[] = $idPa;
            }
        }

        if (empty($combinationIds)) {
            return false;
        }

        $comboAttrMap = $this->repository->getCombinationAttributes($combinationIds);

        foreach ($comboAttrMap as $comboAttrs) {
            foreach ($ruleGroups as $group) {
                if ($this->ruleMatchesCombination($group['conditions'], $comboAttrs)) {
                    return true;
                }
            }
        }

        return false;
    }
}
