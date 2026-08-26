<?php
/**
 * MZC Product Fields — closes a gap left by 1.1.6's own repair migration.
 * Confirmed via direct production reproduction (the exact cart from the
 * user's own screen recording): 1.1.6 correctly synced a stuck
 * `customization.quantity` from 0 to the real cart quantity, but left
 * `customization.in_cart` at its original 0 — a SEPARATE column core's own
 * `_updateCustomizationQuantity()` normally sets to 1 in the same UPDATE that
 * fills in the real quantity (see Cart.php's own native flow). Without it,
 * `Product::getAllCustomizedDatas()` — called with `$only_in_cart = true` by
 * `CartPresenter::addCustomizedData()` — filters `AND c.in_cart = 1` and
 * silently excludes the row entirely, so the front-office template's
 * `remove_from_cart_url` never gets rebuilt with the real `id_customization`
 * and keeps omitting it, which is exactly what still broke the delete link
 * even after 1.1.6. Only ever needed for rows already repaired by 1.1.6 (or
 * otherwise already reflecting a real, matching cart_product line) — this is
 * not a gap in the forward-looking fix itself (a fresh add-to-cart already
 * goes through core's own `_updateCustomizationQuantity()`, which sets both
 * columns together correctly, confirmed via direct testing).
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_7(Mzcproductfields $module): bool
{
    $prefix = _DB_PREFIX_;

    return Db::getInstance()->execute(
        'UPDATE `' . $prefix . 'customization` c
         INNER JOIN `' . $prefix . 'cart_product` cp
             ON cp.`id_cart` = c.`id_cart`
             AND cp.`id_product` = c.`id_product`
             AND cp.`id_product_attribute` = c.`id_product_attribute`
             AND cp.`id_customization` = c.`id_customization`
         SET c.`in_cart` = 1
         WHERE c.`in_cart` = 0
         AND c.`quantity` > 0'
    );
}
