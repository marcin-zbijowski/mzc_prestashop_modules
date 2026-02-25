<?php
/**
 * MZC Carrier Attribute — Carrier filter (core delivery option filtering logic)
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
     * Filter delivery options based on attribute rules
     *
     * @param array $deliveryOptionList Passed by reference from the hook
     * @param Cart  $cart
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
            $idAddr = (int) $product['id_address_delivery'];
            $addrCombinations[$idAddr][$idPa] = $idPa;
        }

        if (empty($combinationIds)) {
            return;
        }

        $comboAttrMap = $this->repository->getCombinationAttributes(array_values($combinationIds));
        $rules = $this->repository->getRules();

        if (empty($rules)) {
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
                foreach ($comboAttrMap[$idPa] as $idAttr) {
                    if (!isset($rules[$idAttr])) {
                        continue;
                    }
                    $allowedSets[] = $rules[$idAttr];
                }
            }

            if (empty($allowedSets)) {
                continue;
            }

            $allowedReferences = $allowedSets[0];
            for ($i = 1, $count = count($allowedSets); $i < $count; $i++) {
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
     * Check if a cart has any carrier restrictions based on attribute rules
     *
     * @param Cart $cart
     *
     * @return bool
     */
    public function cartHasRestrictions(Cart $cart)
    {
        $rules = $this->repository->getRules();
        if (empty($rules)) {
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

        foreach ($comboAttrMap as $attrs) {
            foreach ($attrs as $idAttr) {
                if (isset($rules[$idAttr])) {
                    return true;
                }
            }
        }

        return false;
    }
}
