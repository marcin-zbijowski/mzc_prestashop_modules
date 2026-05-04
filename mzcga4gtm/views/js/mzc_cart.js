/**
 * MZC GA4 cart event tracker.
 *
 * Loaded on every front page. Handles cart-related GA4 events via PS8 events,
 * without requiring a page reload.
 *
 * Events handled:
 *   add_to_cart / remove_from_cart — via prestashop.on('updateCart')
 *     Fires on user intent (before AJAX). Uses product data embedded by PHP:
 *       window.mzc_ga4_product       — product page (single item)
 *       window.mzc_ga4_products_map  — listing/search page (map by item_id)
 *     Falls back to scanning dataLayer history.
 *
 *   view_cart — via prestashop.on('updatedCart')
 *     Fires after the AJAX response, when the cart popup/sidebar becomes visible.
 *     Items are built from event.resp.cart.products (PS8 cart AJAX response),
 *     enriched with embedded GA4 data where available (for categories, brand etc.).
 *     Falls back to a basic item if enrichment data is unavailable.
 */
(function () {
    'use strict';

    window.dataLayer = window.dataLayer || [];

    /**
     * Finds a GA4 item object by product and attribute IDs.
     * Checks page-scoped variables first (O(1)), then dataLayer history (O(n)).
     *
     * @param {string} idProduct            Product ID as string
     * @param {string} idProductAttribute   Attribute ID as string ('0' if none)
     * @returns {Object|null}
     */
    function findItem(idProduct, idProductAttribute) {
        var hasAttr    = idProductAttribute && idProductAttribute !== '0';
        var langPrefix = (window.mzc_ga4_lang_prefix || '').toUpperCase();
        var keyWithAttr = langPrefix + idProduct + 'v' + idProductAttribute;
        var keyBase     = langPrefix + idProduct;

        // 1. Single product page
        if (window.mzc_ga4_product) {
            var p = window.mzc_ga4_product;
            if (p.item_id === (hasAttr ? keyWithAttr : keyBase) || p.item_id === keyBase) {
                return p;
            }
        }

        // 2. Listing / search page map
        if (window.mzc_ga4_products_map) {
            var m = window.mzc_ga4_products_map;
            if (hasAttr && m[keyWithAttr]) { return m[keyWithAttr]; }
            if (m[keyBase]) { return m[keyBase]; }
        }

        // 3. Fallback: reverse-scan dataLayer for any previous push containing this item
        var dl = window.dataLayer;
        for (var i = dl.length - 1; i >= 0; i--) {
            var entry = dl[i];
            if (!entry || !Array.isArray(entry.items)) { continue; }
            for (var j = 0; j < entry.items.length; j++) {
                var candidate = entry.items[j];
                if (candidate.item_id === (hasAttr ? keyWithAttr : keyBase) ||
                    candidate.item_id === keyBase) {
                    return candidate;
                }
            }
        }

        return null;
    }

    /**
     * Shallow-copies an item object and overrides quantity.
     */
    function cloneItemWithQty(item, qty) {
        var copy = {};
        for (var k in item) {
            if (Object.prototype.hasOwnProperty.call(item, k)) {
                copy[k] = item[k];
            }
        }
        copy.quantity = qty;
        return copy;
    }

    if (!window.prestashop) { return; }

    /**
     * prestashop.on('updateCart') fires synchronously when a cart action starts.
     *
     * event.reason fields (PS8 Classic + most custom themes):
     *   linkAction           — 'add-to-cart' | 'delete-product' | 'update-quantity'
     *   idProduct            — product ID (number or string)
     *   idProductAttribute   — attribute/combination ID (0 if none)
     *   quantity             — requested quantity delta
     */
    prestashop.on('updateCart', function (event) {
        var reason = (event && event.reason) ? event.reason : {};
        var action = String(reason.linkAction || '');

        // Only handle explicit add/remove actions.
        // 'update-quantity' is ambiguous (could be + or -), skip it here.
        if (action !== 'add-to-cart' && action !== 'delete-product') { return; }

        var idProduct  = String(reason.idProduct  || reason.id_product  || '');
        var idAttr     = String(reason.idProductAttribute || reason.id_product_attribute || '0');
        var qty        = Math.abs(parseInt(reason.quantity, 10) || 1);

        if (!idProduct || idProduct === '0') { return; }

        var baseItem = findItem(idProduct, idAttr);
        if (!baseItem) { return; } // no item data available — skip rather than send garbage

        var eventName = (action === 'delete-product') ? 'remove_from_cart' : 'add_to_cart';
        var cartItem  = cloneItemWithQty(baseItem, qty);

        window.dataLayer.push({ecommerce: null});
        window.dataLayer.push({
            event:     eventName,
            ecommerce: {
                currency: window.mzc_ga4_currency || '',
                value:    parseFloat((baseItem.price * qty).toFixed(2)),
                items:    [cartItem]
            }
        });
    });

    /**
     * prestashop.on('updatedCart') fires after the cart AJAX response is received
     * and the cart popup/sidebar is shown. This is the right moment for view_cart.
     *
     * event.resp.cart structure (PS8 CartController::displayAjaxUpdate):
     *   products[]
     *     id_product, id_product_attribute
     *     name, cart_quantity (or quantity)
     *     price_amount | price_wt | price  — gross unit price (various PS8 versions)
     *   totals.total.amount  — gross cart total
     */
    prestashop.on('updatedCart', function (event) {
        var resp     = (event && event.resp)      ? event.resp      : {};
        var cart     = resp.cart                  ? resp.cart       : {};
        var products = Array.isArray(cart.products) ? cart.products : [];

        if (!products.length) { return; }

        var currency   = window.mzc_ga4_currency    || '';
        var langPrefix = (window.mzc_ga4_lang_prefix || '').toUpperCase();
        var items      = [];

        for (var i = 0; i < products.length; i++) {
            var p      = products[i];
            var idProd = String(p.id_product             || '');
            var idAttr = String(p.id_product_attribute   || '0');
            var qty    = parseInt(p.cart_quantity || p.quantity, 10) || 1;

            if (!idProd || idProd === '0') { continue; }

            // Try to use enriched item data (with categories, brand) from page vars
            var enriched = findItem(idProd, idAttr);
            if (enriched) {
                items.push(cloneItemWithQty(enriched, qty));
            } else {
                // Fallback: basic item from AJAX response data (no categories/brand)
                var hasAttr = idAttr && idAttr !== '0';
                var price   = parseFloat(p.price_amount || p.price_wt || p.price || 0);
                items.push({
                    item_id:   langPrefix + (hasAttr ? idProd + 'v' + idAttr : idProd),
                    item_name: String(p.name || ''),
                    price:     parseFloat(price.toFixed(2)),
                    quantity:  qty
                });
            }
        }

        if (!items.length) { return; }

        // Total from cart response; fall back to summing items if unavailable
        var value = 0;
        if (cart.totals && cart.totals.total && cart.totals.total.amount !== undefined) {
            value = parseFloat(cart.totals.total.amount);
        } else {
            for (var j = 0; j < items.length; j++) {
                value += (items[j].price || 0) * (items[j].quantity || 1);
            }
        }

        window.dataLayer.push({ecommerce: null});
        window.dataLayer.push({
            event:     'view_cart',
            ecommerce: {
                currency: currency,
                value:    parseFloat(value.toFixed(2)),
                items:    items
            }
        });
    });

}());
