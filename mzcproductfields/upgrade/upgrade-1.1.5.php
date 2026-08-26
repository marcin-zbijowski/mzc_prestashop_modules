<?php
/**
 * MZC Product Fields — registers the new `actionCartSave` hook (see
 * Mzcproductfields::hookActionCartSave()'s docblock: repairs a native
 * PrestaShop core inconsistency where `cart_product.id_customization` is
 * never synced to the real `customization.id_customization`, which breaks
 * removing/updating the quantity of the last unit of any customized
 * product), and runs a ONE-TIME retroactive repair across every currently
 * live cart — not just this module's own carts — so customers with a
 * cart already stuck from this bug are fixed immediately on deploy, not
 * only from their next cart change onward.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_5(Mzcproductfields $module): bool
{
    if (!$module->registerHook('actionCartSave')) {
        return false;
    }

    $prefix = _DB_PREFIX_;

    // LEFT JOIN + IS NULL guard: cart_product's primary key includes
    // id_customization, so blindly UPDATE-ing every 0 row to its real id
    // would hit a duplicate-key error the moment a row already exists at
    // that target key (confirmed hitting this during testing on carts with
    // pre-existing leftover data) — skip just that one pair rather than
    // aborting the whole repair.
    return Db::getInstance()->execute(
        'UPDATE `' . $prefix . 'cart_product` cp
         INNER JOIN (
             SELECT `id_cart`, `id_product`, `id_product_attribute`, MIN(`id_customization`) AS `real_id_customization`
             FROM `' . $prefix . 'customization`
             GROUP BY `id_cart`, `id_product`, `id_product_attribute`
             HAVING COUNT(*) = 1
         ) cu ON cu.`id_cart` = cp.`id_cart` AND cu.`id_product` = cp.`id_product` AND cu.`id_product_attribute` = cp.`id_product_attribute`
         LEFT JOIN `' . $prefix . 'cart_product` cp2
             ON cp2.`id_cart` = cp.`id_cart`
             AND cp2.`id_product` = cp.`id_product`
             AND cp2.`id_address_delivery` = cp.`id_address_delivery`
             AND cp2.`id_product_attribute` = cp.`id_product_attribute`
             AND cp2.`id_customization` = cu.`real_id_customization`
         SET cp.`id_customization` = cu.`real_id_customization`
         WHERE cp.`id_customization` = 0
         AND cp2.`id_customization` IS NULL'
    );
}
