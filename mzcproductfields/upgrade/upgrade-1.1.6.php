<?php
/**
 * MZC Product Fields — the real, deeper root cause behind "can't remove a
 * personalized product from the cart" (the 1.1.5 `actionCartSave` fix
 * addressed a real but secondary inconsistency; this is the fix that
 * actually stops it from happening in the first place). Confirmed via direct
 * production reproduction: every product this module ever attached a field
 * to has `product.customizable` left at 0, because
 * CustomizationFieldSynchronizer::getOrCreate() creates `customization_field`
 * rows directly rather than through the native admin product form (which is
 * what normally flips that flag). With `customizable = 0`, core's own
 * `Cart::updateQty()` skips its `_updateCustomizationQuantity()` call
 * entirely (gated on `if ($product->customizable)`), so the `customization`
 * row `_addCustomization()` creates — quantity intentionally 0 as a
 * placeholder, expecting that later core call to fill in the real quantity —
 * never gets updated and stays at quantity 0 forever. That breaks
 * `Cart::getProducts()`'s own customization JOIN and the front-office's
 * presented `id_customization` for the line, which cascades into
 * `Cart::deleteProduct()`'s `cart_product` DELETE never matching the real
 * row. See CustomizationFieldSynchronizer::ensureProductMarkedCustomizable()
 * for the forward-looking fix (every future field attach now sets this flag)
 * — this migration is the one-time retroactive repair for products and
 * carts already affected.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_6(Mzcproductfields $module): bool
{
    $prefix = _DB_PREFIX_;
    $db = Db::getInstance();

    // 1. Every product this module has ever attached a field to should have
    // been marked customizable from the start — repair it now so future
    // Cart::updateQty() calls stop skipping _updateCustomizationQuantity().
    // Both `product` and `product_shop` carry their own `customizable`
    // column (multistore) — the `Product` object actually resolves the
    // property at runtime from `product_shop`, not `product`, so both must
    // be repaired or this has silently no effect at all.
    $productsFixed = $db->execute(
        'UPDATE `' . $prefix . 'product` p
         INNER JOIN (
             SELECT DISTINCT `id_product` FROM `' . $prefix . 'mzc_productfields_field_customization`
         ) fc ON fc.`id_product` = p.`id_product`
         SET p.`customizable` = 1
         WHERE p.`customizable` = 0'
    );

    $productsShopFixed = $db->execute(
        'UPDATE `' . $prefix . 'product_shop` ps
         INNER JOIN (
             SELECT DISTINCT `id_product` FROM `' . $prefix . 'mzc_productfields_field_customization`
         ) fc ON fc.`id_product` = ps.`id_product`
         SET ps.`customizable` = 1
         WHERE ps.`customizable` = 0'
    );

    if (!$productsFixed || !$productsShopFixed) {
        return false;
    }

    // 2. Any customization row already stuck at quantity 0 while its
    // matching cart_product line shows a real quantity (only where their
    // id_product_attribute actually agrees — a different, older mismatch is
    // a separate, already-identified legacy issue this deliberately leaves
    // untouched rather than guess at) gets its quantity repaired directly,
    // unsticking carts that are already affected right now rather than only
    // preventing new occurrences.
    return $db->execute(
        'UPDATE `' . $prefix . 'customization` c
         INNER JOIN `' . $prefix . 'cart_product` cp
             ON cp.`id_cart` = c.`id_cart`
             AND cp.`id_product` = c.`id_product`
             AND cp.`id_product_attribute` = c.`id_product_attribute`
         SET c.`quantity` = cp.`quantity`
         WHERE c.`quantity` = 0
         AND cp.`quantity` > 0'
    );
}
