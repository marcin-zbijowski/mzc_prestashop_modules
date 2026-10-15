<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/MzcItemMapper.php';
require_once __DIR__ . '/classes/MzcDataLayer.php';

/**
 * MZC GA4 / GTM DataLayer module for PrestaShop 8 / 9.
 *
 * The single place for the shop's analytics:
 *
 *   - loads the Google Tag Manager container (snippet in <head>, <noscript>
 *     iframe after <body>), when a container ID is configured and switched on;
 *   - pushes structured GA4 events to window.dataLayer.
 *
 * Google Tag Manager receives those events and routes them to GA4. This module
 * never sends data to GA4 directly, and replaces a hand-edited ps_googleanalytics.
 *
 * Consent is NOT handled here. megacookies sets the Consent Mode defaults (an
 * inline script in its displayHeader output) and updates them from its worker
 * script. This module only has to make sure that script runs BEFORE gtm.js, see
 * ensureHeaderOrder() — and, when megacookies is not active, to push a denied
 * default itself so that GTM never starts without one.
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

    /** Configuration: the container ID ("GTM-XXXXXXX"); empty means "load nothing". */
    const CONFIG_GTM_ID = 'MZC_GTM_ID';

    /** Configuration: master switch for loading the container. */
    const CONFIG_GTM_ENABLED = 'MZC_GTM_ENABLED';

    /** What a container ID looks like. Uppercase letters and digits after "GTM-". */
    const GTM_ID_PATTERN = '/^GTM-[A-Z0-9]{4,10}$/';

    /** The production container and domain — only used to warn in the panel, never to block. */
    const PRODUCTION_GTM_ID = 'GTM-5HCL8MXQ';
    const PRODUCTION_HOST   = 'artedeum.pl';

    /** The module whose header script owns Consent Mode defaults. */
    const CONSENT_MODULE = 'megacookies';

    const TRANSLATION_DOMAIN = 'Modules.Mzcga4gtm.Admin';

    public function __construct()
    {
        $this->name             = 'mzcga4gtm';
        $this->tab              = 'analytics_stats';
        $this->version          = '1.1.0';
        $this->author           = 'Marcin Zbijowski Consulting';
        $this->need_instance    = 0;
        $this->bootstrap        = true;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->trans('MZC GA4 / GTM DataLayer', [], self::TRANSLATION_DOMAIN);
        $this->description = $this->trans(
            'Loads Google Tag Manager and pushes structured GA4 events to dataLayer.',
            [],
            self::TRANSLATION_DOMAIN
        );
    }

    // =========================================================================
    // Install / Uninstall
    // =========================================================================

    public function install(): bool
    {
        // The container ID starts empty and the switch off: a fresh install, on
        // staging above all, must not start sending data to the production container.
        return parent::install()
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayAfterBodyOpeningTag')
            && $this->registerHook('displayOrderConfirmation')
            && $this->registerHook('displayBeforeBodyClosingTag')
            && Configuration::updateValue(self::CONFIG_GTM_ID, '')
            && Configuration::updateValue(self::CONFIG_GTM_ENABLED, 0)
            && $this->ensureHeaderOrder();
    }

    public function uninstall(): bool
    {
        return parent::uninstall()
            && Configuration::deleteByName(self::CONFIG_GTM_ID)
            && Configuration::deleteByName(self::CONFIG_GTM_ENABLED);
    }

    // =========================================================================
    // Hooks
    // =========================================================================

    /**
     * displayHeader — initializes dataLayer, pushes page context and, when it is
     * configured, loads the Google Tag Manager container.
     *
     * Order inside this output matters: dataLayer and the page context first, the
     * container snippet last. megacookies' own displayHeader output (the consent
     * defaults) has to come before all of it, which is what ensureHeaderOrder()
     * arranges between the two modules.
     */
    public function hookDisplayHeader(array $params): string
    {
        $output = MzcDataLayer::renderInit(
            $this->context->currency->iso_code,
            $this->context->language->iso_code
        );

        $gtmId = $this->getActiveGtmId();

        if ($gtmId !== null) {
            $output .= MzcDataLayer::renderGtmSnippet($gtmId, !$this->isConsentModuleActive());
        }

        return $output;
    }

    /**
     * displayAfterBodyOpeningTag — the <noscript> iframe GTM asks for right after
     * <body>. Same conditions as the head snippet.
     */
    public function hookDisplayAfterBodyOpeningTag(array $params): string
    {
        $gtmId = $this->getActiveGtmId();

        return $gtmId !== null ? MzcDataLayer::renderGtmNoscript($gtmId) : '';
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
    // Google Tag Manager: configuration and conditions
    // =========================================================================

    /**
     * The container ID to load on this request, or null when nothing may load.
     *
     * Null when the switch is off, the ID is empty or malformed, or the page is
     * an administrator's preview of the shop (PrestaShop adds `adtoken` to those
     * URLs) — a preview must not count as a visit. The back office itself never
     * gets here: displayHeader and displayAfterBodyOpeningTag are front hooks.
     */
    private function getActiveGtmId(): ?string
    {
        if (!Configuration::get(self::CONFIG_GTM_ENABLED)) {
            return null;
        }

        $id = self::normalizeGtmId((string) Configuration::get(self::CONFIG_GTM_ID));

        if ($id === '' || !preg_match(self::GTM_ID_PATTERN, $id)) {
            return null;
        }

        if (Tools::getValue('adtoken') || Tools::getIsset('live_edit')) {
            return null;
        }

        return $id;
    }

    private static function normalizeGtmId(string $id): string
    {
        return strtoupper(trim($id));
    }

    /**
     * Is megacookies installed and switched on? It then owns Consent Mode.
     */
    private function isConsentModuleActive(): bool
    {
        return Module::isInstalled(self::CONSENT_MODULE) && Module::isEnabled(self::CONSENT_MODULE);
    }

    /**
     * Makes this module's displayHeader output come AFTER megacookies'.
     *
     * megacookies' header script pushes the Consent Mode defaults to dataLayer.
     * GTM reads dataLayer in order, so the default has to be in the queue before
     * the `gtm.js` event this module's snippet adds; the other way round, the
     * container starts without a consent state.
     *
     * Two things can put them the wrong way round, and both are repaired here:
     *
     *  1. megacookies registers itself on the old hook name `header`. On
     *     PrestaShop 9 that is a hook row of its own, an alias of displayHeader,
     *     and Hook::getHookModuleExecList() runs alias registrations AFTER every
     *     module registered on displayHeader itself, whatever the positions.
     *     No position can fix that, so the registration is moved to displayHeader.
     *     megacookies keeps working there: the core calls its hookHeader() through
     *     the retro hook name.
     *  2. On the same hook row, the order is the module's `position`. This module is
     *     moved to just below megacookies when it is above it. Nothing else is reordered.
     *
     * Called on install, on upgrade and on every save of the settings, so a
     * reinstall of megacookies, or a manual drag in Design → Positions, is repaired
     * the next time anyone opens the panel and saves.
     */
    public function ensureHeaderOrder(): bool
    {
        if (!$this->id || !$this->isConsentModuleActive()) {
            return true;
        }

        $idHook    = (int) Hook::getIdByName('displayHeader');
        $idConsent = (int) Module::getModuleIdByName(self::CONSENT_MODULE);

        if (!$idHook || !$idConsent) {
            return true;
        }

        $db = Db::getInstance();

        // 1. Move megacookies from the alias row to displayHeader.
        $consent = null;

        if (!$this->isRegisteredOn($idConsent, $idHook)) {
            $consent = Module::getInstanceByName(self::CONSENT_MODULE);

            if (!$consent || !$consent->registerHook('displayHeader')) {
                return false;
            }
        }

        // Whenever it is also (or still) on an alias row, drop that one — it only adds
        // an out-of-order registration. By hook id, never by name: a name resolves to
        // the alias target and would remove the registration that matters.
        foreach ($this->getHeaderAliasHookIds() as $idAlias) {
            if ($this->isRegisteredOn($idConsent, $idAlias)) {
                $consent = $consent ?: Module::getInstanceByName(self::CONSENT_MODULE);

                if ($consent) {
                    $consent->unregisterHook($idAlias);
                }
            }
        }

        // 2. Put this module just below megacookies on displayHeader.
        foreach (Shop::getContextListShopID() as $idShop) {
            $rows = $db->executeS(
                'SELECT `id_module` FROM `' . _DB_PREFIX_ . 'hook_module`
                WHERE `id_hook` = ' . $idHook . ' AND `id_shop` = ' . (int) $idShop . '
                ORDER BY `position` ASC, `id_module` ASC'
            );
            $ids = array_map('intval', array_column((array) $rows, 'id_module'));

            $consentAt = array_search($idConsent, $ids, true);
            $ownAt     = array_search((int) $this->id, $ids, true);

            if ($consentAt === false || $ownAt === false || $ownAt > $consentAt) {
                continue;
            }

            unset($ids[$ownAt]);
            $ids = array_values($ids);
            array_splice($ids, (int) array_search($idConsent, $ids, true) + 1, 0, [(int) $this->id]);

            foreach ($ids as $position => $idModule) {
                $db->update(
                    'hook_module',
                    ['position' => $position + 1],
                    '`id_hook` = ' . $idHook . ' AND `id_module` = ' . (int) $idModule . ' AND `id_shop` = ' . (int) $idShop
                );
            }
        }

        Cache::clean('hook_module_list');
        Cache::clean('hook_module_exec_list_*');

        return true;
    }

    /**
     * Hook row ids that are aliases of displayHeader ("Header").
     *
     * @return int[]
     */
    private function getHeaderAliasHookIds(): array
    {
        // hook_alias.name is the canonical hook, hook_alias.alias the old name
        // ("Header") that modules written for PrestaShop 1.6 still register on.
        $aliases = Db::getInstance()->executeS(
            'SELECT `alias` FROM `' . _DB_PREFIX_ . 'hook_alias` WHERE LOWER(`name`) = \'displayheader\''
        );
        $names = array_map('strtolower', array_column((array) $aliases, 'alias'));

        if (!$names) {
            return [];
        }

        $in = implode(',', array_map(static fn (string $name): string => "'" . pSQL($name) . "'", $names));
        $rows = Db::getInstance()->executeS(
            'SELECT `id_hook` FROM `' . _DB_PREFIX_ . 'hook` WHERE LOWER(`name`) IN (' . $in . ')'
        );

        return array_map('intval', array_column((array) $rows, 'id_hook'));
    }

    private function isRegisteredOn(int $idModule, int $idHook): bool
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'hook_module`
            WHERE `id_module` = ' . $idModule . ' AND `id_hook` = ' . $idHook . '
            AND `id_shop` = ' . (int) $this->context->shop->id
        );
    }

    /**
     * True when megacookies' header output is already printed before this module's:
     * it is registered on displayHeader itself and sits above this module. Vacuously
     * true when megacookies is not in play.
     */
    private function isHeaderOrderRight(): bool
    {
        if (!$this->id || !$this->isConsentModuleActive()) {
            return true;
        }

        $idHook = (int) Hook::getIdByName('displayHeader');
        $rows   = Db::getInstance()->executeS(
            'SELECT `id_module`, `position` FROM `' . _DB_PREFIX_ . 'hook_module`
            WHERE `id_hook` = ' . $idHook . ' AND `id_shop` = ' . (int) $this->context->shop->id
        );
        $positions = [];

        foreach ((array) $rows as $row) {
            $positions[(int) $row['id_module']] = (int) $row['position'];
        }

        $own     = $positions[(int) $this->id] ?? null;
        $consent = $positions[(int) Module::getModuleIdByName(self::CONSENT_MODULE)] ?? null;

        // No registration of megacookies on displayHeader itself: it is on the alias row and
        // runs after everything here.
        if ($consent === null) {
            return false;
        }

        return $own === null || $consent < $own;
    }

    /**
     * Does the shop's own domain look like the production one?
     */
    private function isProductionHost(): bool
    {
        $hosts = [
            (string) $this->context->shop->domain,
            (string) $this->context->shop->domain_ssl,
            (string) Tools::getHttpHost(false),
        ];

        foreach ($hosts as $host) {
            $host = strtolower(trim($host));
            $host = (string) preg_replace('/^www\./', '', $host);

            if ($host === self::PRODUCTION_HOST) {
                return true;
            }
        }

        return false;
    }

    // =========================================================================
    // Back office
    // =========================================================================

    /**
     * The configuration page: the container ID and the switch.
     *
     * The form is a POST to the module's own route; the core refuses that request
     * without a valid admin token before this method runs, so there is no separate
     * token check here.
     */
    public function getContent(): string
    {
        $html = '';

        if (Tools::isSubmit('submitMzcGtm')) {
            $html .= $this->processSettings();
        }

        return $html . $this->renderWarnings() . $this->renderSettingsForm();
    }

    private function processSettings(): string
    {
        $id      = self::normalizeGtmId((string) Tools::getValue(self::CONFIG_GTM_ID));
        $enabled = (bool) Tools::getValue(self::CONFIG_GTM_ENABLED);

        if ($id !== '' && !preg_match(self::GTM_ID_PATTERN, $id)) {
            return $this->displayError($this->trans(
                'The container ID must look like GTM-XXXXXXX: "GTM-" followed by letters and digits.',
                [],
                self::TRANSLATION_DOMAIN
            ));
        }

        Configuration::updateValue(self::CONFIG_GTM_ID, $id);
        Configuration::updateValue(self::CONFIG_GTM_ENABLED, $enabled ? 1 : 0);
        $this->ensureHeaderOrder();

        return $this->displayConfirmation($this->trans('Settings saved.', [], self::TRANSLATION_DOMAIN));
    }

    /**
     * Things worth knowing before leaving the page. Warnings only: none of them
     * blocks saving or loading.
     */
    private function renderWarnings(): string
    {
        $html    = '';
        $id      = self::normalizeGtmId((string) Configuration::get(self::CONFIG_GTM_ID));
        $enabled = (bool) Configuration::get(self::CONFIG_GTM_ENABLED);

        if ($id === self::PRODUCTION_GTM_ID && !$this->isProductionHost()) {
            $html .= $this->displayWarning($this->trans(
                'This is the production container (%id%), but this shop is not on %host%. Visits here will be counted in the production data.',
                ['%id%' => self::PRODUCTION_GTM_ID, '%host%' => self::PRODUCTION_HOST],
                self::TRANSLATION_DOMAIN
            ));
        }

        if ($enabled && $id === '') {
            $html .= $this->displayWarning($this->trans(
                'Loading is switched on, but no container ID is set, so nothing is loaded.',
                [],
                self::TRANSLATION_DOMAIN
            ));
        }

        if ($enabled && $id !== '') {
            if (!$this->isConsentModuleActive()) {
                $html .= $this->displayWarning($this->trans(
                    'The consent module (%module%) is not active. Google Tag Manager starts with all consents denied and nothing will ever grant them.',
                    ['%module%' => self::CONSENT_MODULE],
                    self::TRANSLATION_DOMAIN
                ));
            } elseif (!$this->isHeaderOrderRight()) {
                $html .= $this->displayWarning($this->trans(
                    'The header script of %module% would run after Google Tag Manager starts, so the container would start before the consent defaults. Save the settings to fix the order.',
                    ['%module%' => self::CONSENT_MODULE],
                    self::TRANSLATION_DOMAIN
                ));
            }

            if (Module::isInstalled('ps_googleanalytics') && Module::isEnabled('ps_googleanalytics')) {
                $html .= $this->displayWarning($this->trans(
                    'ps_googleanalytics is still active. Disable it: this module is the only place for analytics, and two of them count every visit twice.',
                    [],
                    self::TRANSLATION_DOMAIN
                ));
            }
        }

        return $html;
    }

    private function renderSettingsForm(): string
    {
        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Google Tag Manager', [], self::TRANSLATION_DOMAIN),
                    'icon'  => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type'  => 'text',
                        'label' => $this->trans('GTM container ID', [], self::TRANSLATION_DOMAIN),
                        'name'  => self::CONFIG_GTM_ID,
                        'class' => 'fixed-width-xl',
                        'desc'  => $this->trans(
                            'For example GTM-XXXXXXX. Leave empty to load nothing — the right choice on a staging copy.',
                            [],
                            self::TRANSLATION_DOMAIN
                        ),
                    ],
                    [
                        'type'    => 'switch',
                        'label'   => $this->trans('Load GTM', [], self::TRANSLATION_DOMAIN),
                        'name'    => self::CONFIG_GTM_ENABLED,
                        'is_bool' => true,
                        'desc'    => $this->trans(
                            'The container loads only when this is on and the ID is valid. It never loads in an administrator\'s preview. Consent Mode defaults and updates come from %module%, not from this module.',
                            ['%module%' => self::CONSENT_MODULE],
                            self::TRANSLATION_DOMAIN
                        ),
                        'values' => [
                            ['id' => 'gtm_on', 'value' => 1, 'label' => $this->trans('Yes', [], self::TRANSLATION_DOMAIN)],
                            ['id' => 'gtm_off', 'value' => 0, 'label' => $this->trans('No', [], self::TRANSLATION_DOMAIN)],
                        ],
                    ],
                ],
                'submit' => ['title' => $this->trans('Save', [], self::TRANSLATION_DOMAIN)],
            ],
        ];

        $posted = Tools::isSubmit('submitMzcGtm');

        $helper = new HelperForm();
        $helper->module         = $this;
        $helper->table          = $this->name;
        $helper->submit_action  = 'submitMzcGtm';
        $helper->token          = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex   = $this->baseUrl();
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->tpl_vars       = [
            'fields_value' => [
                self::CONFIG_GTM_ID      => $posted ? (string) Tools::getValue(self::CONFIG_GTM_ID) : (string) Configuration::get(self::CONFIG_GTM_ID),
                self::CONFIG_GTM_ENABLED => $posted ? (int) Tools::getValue(self::CONFIG_GTM_ENABLED) : (int) Configuration::get(self::CONFIG_GTM_ENABLED),
            ],
            'languages'   => $this->context->controller->getLanguages(),
            'id_language' => (int) $this->context->language->id,
        ];

        return $helper->generateForm([$form]);
    }

    /**
     * Base URL of this configuration page, already carrying a query string.
     * The Symfony router adds the CSRF `_token` on PrestaShop 8 and 9; the legacy
     * form is the fallback.
     */
    private function baseUrl(): string
    {
        $container = \PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance();

        if ($container !== null) {
            try {
                return (string) $container->get('router')->generate('admin_module_configure_action', ['module_name' => $this->name]);
            } catch (\Throwable $e) {
                // Route not defined: fall through to the legacy URL.
            }
        }

        return AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules');
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
