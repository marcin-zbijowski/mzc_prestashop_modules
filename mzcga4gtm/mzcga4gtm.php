<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/MzcItemMapper.php';
require_once __DIR__ . '/classes/MzcDataLayer.php';

/**
 * MZC GA4 / GTM DataLayer module for PrestaShop 8.
 *
 * Pushes structured GA4 events to window.dataLayer.
 * Google Tag Manager receives those events and routes them to GA4.
 * This module never sends data to GA4 directly.
 *
 * Events implemented:
 *   mzc_page_context     — every page (currency, language)
 *   view_item            — product page
 *   view_item_list       — category / search listing
 *   add_to_cart          — via JS (prestashop.on('updateCart')) — immediate, no page reload needed
 *   remove_from_cart     — via JS (prestashop.on('updateCart')) — immediate, no page reload needed
 *   view_cart            — cart page (full page load)
 *   begin_checkout       — checkout page load
 *   add_shipping_info    — delivery step active (via JS)
 *   add_payment_info     — payment step active (via JS)
 *   purchase             — order confirmation (with localStorage deduplication)
 */
class Mzcga4gtm extends Module
{
    /** Controllers that host the checkout flow */
    const CHECKOUT_CONTROLLERS = ['order', 'orderopc'];

    public function __construct()
    {
        $this->name             = 'mzcga4gtm';
        $this->tab              = 'analytics_stats';
        $this->version          = '1.0.0';
        $this->author           = 'Marcin Zbijowski Consulting';
        $this->need_instance    = 0;
        $this->bootstrap        = true;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->l('MZC GA4 / GTM DataLayer');
        $this->description = $this->l(
            'Pushes structured GA4 events to dataLayer for Google Tag Manager.'
        );
    }

    // =========================================================================
    // Install / Uninstall
    // =========================================================================

    public function install(): bool
    {
        return parent::install()
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayOrderConfirmation')
            && $this->registerHook('displayBeforeBodyClosingTag');
    }

    public function uninstall(): bool
    {
        return parent::uninstall();
    }

    // =========================================================================
    // Hooks
    // =========================================================================

    /**
     * displayHeader — initializes dataLayer and pushes page context.
     * Must fire before any other push on the page.
     */
    public function hookDisplayHeader(array $params): string
    {
        return MzcDataLayer::renderInit(
            $this->context->currency->iso_code,
            $this->context->language->iso_code
        );
    }

    /**
     * displayOrderConfirmation — fires purchase with localStorage deduplication.
     *
     * The _dedup_key field is consumed by MzcDataLayer::renderPushWithDedup()
     * and never appears in the actual dataLayer payload.
     */
    public function hookDisplayOrderConfirmation(array $params): string
    {
        /** @var Order $order */
        $order = $params['order'] ?? null;

        if (!$order || !Validate::isLoadedObject($order)) {
            return '';
        }

        // Skip error/cancelled orders
        $errorStateId = (int) Configuration::get('PS_OS_ERROR');
        if ($errorStateId > 0 && $order->getCurrentState() === $errorStateId) {
            return '';
        }

        $currency = new Currency((int) $order->id_currency);
        $cart     = new Cart((int) $order->id_cart);
        $mapper   = new MzcItemMapper($this->context);

        $items = Validate::isLoadedObject($cart)
            ? $mapper->mapCartToItems($cart)
            : $mapper->mapOrderToItems($order);

        // Collect coupon names from applied cart rules
        $coupon = '';
        $cartRules = $order->getCartRules();
        if (!empty($cartRules)) {
            $coupon = implode(',', array_column($cartRules, 'name'));
        }

        return MzcDataLayer::renderPushWithDedup([
            'event'          => 'purchase',
            'transaction_id' => (string) $order->id,
            'currency'       => $currency->iso_code,
            'value'          => round((float) $order->total_paid_tax_incl, 2),
            'tax'            => round((float) $order->total_paid_tax_incl - (float) $order->total_paid_tax_excl, 2),
            'shipping'       => round((float) $order->total_shipping_tax_incl, 2),
            'coupon'         => $coupon,
            'items'          => $items,
            '_dedup_key'     => 'mzc_ga4_purch_' . $order->id,
        ]);
    }

    /**
     * displayBeforeBodyClosingTag — emits all dataLayer pushes and loads JS trackers.
     *
     * Responsibilities (in order):
     *   1. view_item        — product page (Product object from URL param)
     *   2. view_item_list   — listing/search pages (Smarty $listing variable)
     *   3. view_cart        — cart page
     *   4. mzc_cart.js      — loaded on all pages for JS-based add/remove_from_cart
     *   5. begin_checkout + mzc_checkout.js — checkout pages only
     *
     * All display hooks use displayBeforeBodyClosingTag (a PrestaShop core hook,
     * always fired) instead of theme-specific hooks like displayFooterProduct,
     * displayShoppingCart, or displayProductListItem which require the active
     * theme to explicitly call them.
     */
    public function hookDisplayBeforeBodyClosingTag(array $params): string
    {
        $output         = '';
        $controllerName = $this->context->controller->php_self ?? '';
        $currencyIso    = $this->context->currency->iso_code;

        // 1. view_item — product page --------------------------------------------
        if ($controllerName === 'product') {
            $output .= $this->buildViewItemPush();
        }

        // 2. view_item_list — listing / search pages ----------------------------
        $listingControllers = ['category', 'search', 'manufacturer', 'supplier', 'prices-drop', 'new-products', 'best-sales'];
        if (in_array($controllerName, $listingControllers, true)) {
            $output .= $this->buildViewItemListPush($controllerName);
        }

        // 3. view_cart — full cart page ------------------------------------------
        if ($controllerName === 'cart') {
            $cart = $this->context->cart;
            if (Validate::isLoadedObject($cart) && !empty($cart->getProducts())) {
                $mapper = new MzcItemMapper($this->context);
                $output .= MzcDataLayer::renderPush([
                    'event'    => 'view_cart',
                    'currency' => $currencyIso,
                    'value'    => round((float) $cart->getOrderTotal(true), 2),
                    'items'    => $mapper->mapCartToItems($cart),
                ]);
            }
        }

        // 4. mzc_cart.js — loaded on all front pages (handles add/remove_from_cart
        //    immediately via prestashop.on('updateCart') without requiring page reload)
        $cartJsUrl = $this->_path . 'views/js/mzc_cart.js';
        $output   .= '<script src="' . htmlspecialchars($cartJsUrl, ENT_QUOTES) . '"></script>' . "\n";

        // 5. Checkout-specific output --------------------------------------------
        if (!in_array($controllerName, self::CHECKOUT_CONTROLLERS, true)) {
            return $output;
        }

        $cart = $this->context->cart;
        if (!Validate::isLoadedObject($cart) || empty($cart->getProducts())) {
            return $output;
        }

        $mapper    = new MzcItemMapper($this->context);
        $items     = $mapper->mapCartToItems($cart);
        $cartValue = round((float) $cart->getOrderTotal(true), 2);

        // begin_checkout push
        $output .= MzcDataLayer::renderPush([
            'event'    => 'begin_checkout',
            'currency' => $currencyIso,
            'value'    => $cartValue,
            'items'    => $items,
        ]);

        // Expose cart value and currency for mzc_checkout.js
        $currencyJson = json_encode($currencyIso, JSON_HEX_TAG);
        $output .= '<script>' . "\n"
            . 'window.mzc_ga4_currency   = ' . $currencyJson . ';' . "\n"
            . 'window.mzc_ga4_cart_value = ' . $cartValue . ';' . "\n"
            . '</script>' . "\n";

        $checkoutJsUrl = $this->_path . 'views/js/mzc_checkout.js';
        $output       .= '<script src="' . htmlspecialchars($checkoutJsUrl, ENT_QUOTES) . '"></script>' . "\n";

        return $output;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Builds the view_item push for the current product page.
     *
     * Uses Tools::getValue('id_product') + a fresh Product object so this
     * works regardless of whether the theme calls displayFooterProduct.
     * If a combination is selected in the URL, price and item_id reflect it.
     */
    private function buildViewItemPush(): string
    {
        $idProduct = (int) Tools::getValue('id_product');
        if ($idProduct <= 0) {
            return '';
        }

        $idAttr  = (int) Tools::getValue('id_product_attribute', 0);
        $product = new Product($idProduct, true, (int) $this->context->language->id);

        if (!Validate::isLoadedObject($product)) {
            return '';
        }

        $mapper = new MzcItemMapper($this->context);
        $item   = $mapper->mapProductToItem($product);

        if ($idAttr > 0) {
            $langPrefix      = strtoupper($this->context->language->iso_code);
            $item['item_id'] = "{$langPrefix}{$idProduct}v{$idAttr}";
            $item['price']   = round((float) Product::getPriceStatic($idProduct, true, $idAttr), 2);
        }

        $output = MzcDataLayer::renderPush([
            'event'    => 'view_item',
            'currency' => $this->context->currency->iso_code,
            'value'    => $item['price'],
            'items'    => [$item],
        ]);

        // Expose item data for mzc_cart.js so add_to_cart can be pushed immediately
        // without a page reload when the user clicks the add-to-cart button.
        $itemJson = json_encode($item, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $output  .= '<script>window.mzc_ga4_product=' . $itemJson . ';</script>' . "\n";

        return $output;
    }

    /**
     * Builds the view_item_list push for the current listing/search page.
     *
     * Reads $listing from Smarty — it is set by AbstractProductSearchController
     * before template rendering and remains available throughout the request,
     * including at displayBeforeBodyClosingTag time.
     *
     * $listing['products'] contains presented product arrays (ProductLazyArray
     * or plain arrays depending on PS version). MzcItemMapper handles both.
     *
     * @param string $controllerName  php_self value of the current controller
     */
    private function buildViewItemListPush(string $controllerName): string
    {
        $listing  = $this->context->smarty->getTemplateVars('listing');
        $products = $listing['products'] ?? [];

        if (empty($products)) {
            return '';
        }

        $mapper = new MzcItemMapper($this->context);
        $items  = [];

        foreach ($products as $product) {
            $item          = $mapper->mapProductToItem($product);
            $item['index'] = count($items);
            $items[]       = $item;
        }

        if (empty($items)) {
            return '';
        }

        $listName = !empty($listing['label']) ? (string) $listing['label'] : $controllerName;

        $output = MzcDataLayer::renderPush([
            'event'          => 'view_item_list',
            'item_list_id'   => $controllerName,
            'item_list_name' => $listName,
            'items'          => $items,
        ]);

        // Build a map keyed by item_id for fast lookup in mzc_cart.js
        // so add_to_cart fired from a listing page can find the item data.
        $map = [];
        foreach ($items as $item) {
            $map[$item['item_id']] = $item;
        }
        $mapJson = json_encode($map, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $output .= '<script>window.mzc_ga4_products_map=' . $mapJson . ';</script>' . "\n";

        return $output;
    }
}
