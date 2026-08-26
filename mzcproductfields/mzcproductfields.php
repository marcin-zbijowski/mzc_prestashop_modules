<?php
/**
 * MZC Product Fields
 *
 * Configurable product personalization fields (free or paid, scoped to all
 * products / one category / one product), with a live preview on the product
 * page and a content-confirmation step before add-to-cart. Replaces Anvanto
 * Product Fields (an_productfields) on ArteDeum.pl.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/MzcProductFieldsInstaller.php';
require_once __DIR__ . '/classes/MzcProductFieldsField.php';
require_once __DIR__ . '/classes/MzcProductFieldsFieldValue.php';
require_once __DIR__ . '/classes/MzcProductFieldsFont.php';
require_once __DIR__ . '/classes/MzcProductFieldsBackground.php';
require_once __DIR__ . '/classes/MzcProductFieldsOrderArchive.php';
require_once __DIR__ . '/classes/MzcProductFieldsOrderSnapshot.php';

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Fully qualified rather than a `use` statement: this file starts with
// require_once calls, and a top-level `use` below them reads as if the
// import applied only from that point on.
class Mzcproductfields extends Module implements \PrestaShop\PrestaShop\Core\Module\WidgetInterface
{
    public function __construct()
    {
        $this->name = 'mzcproductfields';
        $this->tab = 'front_office_features';
        $this->version = '1.2.0';
        $this->author = 'Marcin Zbijowski Consulting';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '8.1.0', 'max' => '9.99.99'];

        parent::__construct();

        $this->displayName = $this->l('MZC Product Fields');
        $this->description = $this->l('Configurable product personalization fields with live preview. Replaces Anvanto Product Fields.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module? Product field definitions will be deleted. Archived historical order personalization data is kept.');
    }

    // -------------------------------------------------------------------------
    // Install / uninstall
    // -------------------------------------------------------------------------

    public function install()
    {
        $installer = new MzcProductFieldsInstaller();

        return parent::install()
            && $installer->installDb()
            && $this->installTab()
            && $this->registerHook('actionProductFormBuilderModifier')
            && $this->registerHook('actionAfterUpdateProductFormHandler')
            && $this->registerHook('displayProductAdditionalInfo')
            && $this->registerHook('displayProductPersonalisation')
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayAdminOrderMain')
            && $this->registerHook('displayShoppingCartFooter')
            && $this->registerHook('displayOrderConfirmation')
            && $this->registerHook('actionCartSave')
            && $this->registerHook('displayCustomization');
    }

    public function uninstall()
    {
        $installer = new MzcProductFieldsInstaller();
        $this->cleanupUploadedFonts();
        $this->cleanupUploadedBackgrounds();
        $this->get(\MZConsulting\MzcProductFields\Service\CustomizationFieldSynchronizer::class)->removeAll();

        // Deliberately not touching mzc_productfields_order_archive(+_lang) —
        // see MzcProductFieldsOrderArchive class docblock.
        return $installer->uninstallDb()
            && $this->uninstallTab()
            && parent::uninstall();
    }

    private function cleanupUploadedFonts(): void
    {
        foreach (glob(MzcProductFieldsFont::getUploadDir() . '*') ?: [] as $file) {
            if (is_file($file) && basename($file) !== 'index.php') {
                @unlink($file);
            }
        }
    }

    private function cleanupUploadedBackgrounds(): void
    {
        foreach (glob(MzcProductFieldsBackground::getUploadDir() . '*') ?: [] as $file) {
            if (is_file($file) && basename($file) !== 'index.php') {
                @unlink($file);
            }
        }
    }

    private function installTab(): bool
    {
        $tabId = (int) Tab::getIdFromClassName('AdminMzcProductFieldsFields');
        $tab = new Tab($tabId ?: null);
        $tab->active = 1;
        $tab->class_name = 'AdminMzcProductFieldsFields';
        $tab->route_name = 'mzc_productfields_field_index';
        $tab->icon = 'text_fields';
        $tab->name = [];
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = $this->trans('Product Fields', [], 'Modules.Mzcproductfields.Admin', $lang['locale']);
        }
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminCatalog');
        $tab->module = $this->name;

        return (bool) $tab->save();
    }

    private function uninstallTab(): bool
    {
        $tabId = (int) Tab::getIdFromClassName('AdminMzcProductFieldsFields');
        if (!$tabId) {
            return true;
        }

        $tab = new Tab($tabId);

        return (bool) $tab->delete();
    }

    // -------------------------------------------------------------------------
    // Back-office entry point — the real UI lives in the Symfony controller
    // -------------------------------------------------------------------------

    public function getContent()
    {
        $route = $this->get('router')->generate('mzc_productfields_field_index');
        Tools::redirectAdmin($route);
    }

    // -------------------------------------------------------------------------
    // Product edit page integration (hook (b) from the plan) — the first real
    // handler for actionProductFormBuilderModifier in this codebase: Anvanto
    // registers the hook in install() but never implemented it.
    // -------------------------------------------------------------------------

    public function hookActionProductFormBuilderModifier(array $params): void
    {
        $productId = (int) ($params['id'] ?? 0);
        if ($productId <= 0 || !isset($params['form_builder'])) {
            return;
        }

        $this->get(\MZConsulting\MzcProductFields\Form\Modifier\ProductFieldsFormModifier::class)
            ->modify($productId, $params['form_builder']);
    }

    public function hookActionAfterUpdateProductFormHandler(array $params): void
    {
        $productId = (int) ($params['id'] ?? 0);
        if ($productId <= 0) {
            return;
        }

        $tabData = $params['form_data']['mzc_product_fields'] ?? null;
        if ($tabData === null) {
            // The tab wasn't rendered for this product (e.g. no SCOPE_PRODUCTS
            // fields exist yet) — nothing to sync.
            return;
        }

        $submitted = $tabData['product_field_ids'] ?? null;
        if ($submitted !== null) {
            $idLang = (int) $this->context->language->id;
            $allFieldIds = array_map(
                static fn ($field) => (int) $field['id_mzc_productfields_field'],
                MzcProductFieldsField::getActiveProductScopedFields($idLang)
            );

            $this->get(\MZConsulting\MzcProductFields\Repository\FieldRepository::class)
                ->syncProductMembership($productId, array_map('intval', (array) $submitted), $allFieldIds);
        }
    }

    // -------------------------------------------------------------------------
    // Front-office rendering (plan section 4) — same hook Anvanto uses
    // (displayProductAdditionalInfo), unchanged between PS8/PS9, rendered by
    // every theme in scope (confirmed in both artedeum-pl's live "leon" theme
    // and PS9's "classic" theme) with the add-to-cart form always at
    // id="add-to-cart-or-refresh".
    // -------------------------------------------------------------------------

    /**
     * Registers our front-office CSS/JS — must happen here, not from
     * hookDisplayProductAdditionalInfo(): that hook fires while the body is
     * being rendered, by which point the theme's <head> (built from an
     * earlier, separate Smarty pass) has already been assembled, so
     * registerStylesheet()/registerJavascript() calls made there are
     * silently ineffective (no error — confirmed empirically: the field
     * itself rendered correctly, but its assets never appeared in the page).
     * displayHeader is the hook core's own modules use for exactly this
     * (e.g. productcomments), since it fires early enough.
     */
    public function hookDisplayHeader(): void
    {
        $isProductPage = $this->context->controller instanceof ProductController;

        if ($isProductPage) {
            $idProduct = (int) $this->context->controller->getProduct()->id;
            if ($idProduct <= 0 || !MzcProductFieldsField::getFieldsForProduct($idProduct, (int) $this->context->language->id)) {
                return;
            }
        } elseif (!$this->isHomePageWithShowcase()) {
            // Every other page: nothing of ours renders, so nothing to load.
            return;
        }

        // 'version' => $this->version appends "?{version}" to each asset URL
        // (core's own AbstractAssetManager cache-busting mechanism) so a
        // customer's browser fetches the new file the moment this module's
        // version changes, instead of possibly keeping an old cached copy
        // indefinitely — confirmed as a real risk: without this, a JS/CSS fix
        // shipped here could sit stale in a returning customer's browser
        // cache with no way to know a newer version exists. $this->version
        // must be bumped in the constructor whenever any of these four files
        // change, or this has no effect.
        $this->context->controller->registerJavascript(
            'mzcproductfields-front',
            'modules/' . $this->name . '/views/js/front-product-fields.js',
            ['position' => 'bottom', 'priority' => 150, 'version' => $this->version]
        );
        // The snapshot and content-confirmation scripts both hang off the
        // add-to-cart form and the cart round-trip. The home-page showcase has
        // neither (it is a teaser that hands off to the product page), so
        // loading them there would only add two dead files to every visit.
        if ($isProductPage) {
            $this->context->controller->registerJavascript(
                'mzcproductfields-front-snapshot',
                'modules/' . $this->name . '/views/js/front-snapshot.js',
                ['position' => 'bottom', 'priority' => 151, 'version' => $this->version]
            );
            $this->context->controller->registerJavascript(
                'mzcproductfields-front-confirmation',
                'modules/' . $this->name . '/views/js/front-confirmation.js',
                ['position' => 'bottom', 'priority' => 152, 'version' => $this->version]
            );
        }
        $this->context->controller->registerStylesheet(
            'mzcproductfields-front',
            'modules/' . $this->name . '/views/css/front-product-fields.css',
            ['position' => 'head', 'priority' => 150, 'version' => $this->version]
        );
    }

    /**
     * Alternative placement for the block below.
     *
     * displayProductAdditionalInfo sits at the very end of the add-to-cart form
     * in both the classic and Hummingbird themes, i.e. *after* the add-to-cart
     * button. A theme that wants the personalization higher up — above the
     * combination pickers, where it belongs when it is the main reason someone
     * is on the page — declares this custom hook, hooks us onto it and unhooks
     * us from displayProductAdditionalInfo. Same block either way.
     */
    public function hookDisplayProductPersonalisation(array $params): string
    {
        return $this->hookDisplayProductAdditionalInfo($params);
    }

    public function hookDisplayProductAdditionalInfo(array $params): string
    {
        $idProduct = (int) ($params['product']['id_product'] ?? 0);
        if ($idProduct <= 0) {
            return '';
        }

        $presented = $this->presentProductFields($idProduct);

        if (!$presented['fields']) {
            return '';
        }

        $this->smarty->assign([
            'mzc_fields' => $presented['fields'],
            'mzc_fonts' => $presented['fonts'],
            'mzc_backgrounds' => $presented['backgrounds'],
            'mzc_combination_rules' => $presented['combination_rules'],
            'mzc_id_product' => $idProduct,
            'mzc_save_customization_url' => $this->context->link->getModuleLink($this->name, 'savecustomization'),
            'mzc_save_snapshot_url' => $this->context->link->getModuleLink($this->name, 'savesnapshot'),
            'mzc_i18n' => $this->getConfirmationTranslations(),
        ]);

        return $this->fetch('module:mzcproductfields/views/templates/hook/product_fields.tpl');
    }

    /**
     * Presents one product's fields for the front office.
     *
     * Front-office requests never boot a Symfony kernel (confirmed: `global
     * $kernel` is null here even on PS9) — $this->get() silently returns
     * null in that case (Module::get() swallows ContainerNotFoundException),
     * so unlike the admin-side hooks, this must NOT go through the DI
     * container. Our services have no framework dependencies of their own,
     * so plain `new` is both correct and simpler.
     *
     * @return array{fields:array,fonts:array,backgrounds:array,combination_rules:array}
     */
    private function presentProductFields(int $idProduct): array
    {
        // hookDisplayHeader has to decide about assets before renderWidget()
        // runs, so on the home page every showcase product would otherwise be
        // presented twice per request.
        static $cache = [];
        if (isset($cache[$idProduct])) {
            return $cache[$idProduct];
        }

        $backgroundRepository = new \MZConsulting\MzcProductFields\Repository\BackgroundRepository();
        $presenter = new \MZConsulting\MzcProductFields\Service\ProductFieldsPresenter(
            new \MZConsulting\MzcProductFields\Service\CustomizationPriceCalculator(),
            $backgroundRepository,
            new \MZConsulting\MzcProductFields\Service\CombinationBackgroundResolver($backgroundRepository)
        );

        return $cache[$idProduct] = $presenter->present($idProduct, (int) $this->context->language->id);
    }

    // -------------------------------------------------------------------------
    // Home-page showcase (WidgetInterface)
    //
    // A teaser, deliberately NOT a second product page: it renders the real
    // live preview — real photo, real font, real preview coordinates — for a
    // handful of products, but carries no pricing, no combinations, no
    // content-confirmation step and no add-to-cart. Typing there and clicking
    // through lands on the product page with the text carried over in
    // `mzc_prefill_{id_field}`, where the full block above takes over.
    //
    // Rendering stays in one place: same ProductFieldsPresenter, same
    // front-product-fields.js, only a leaner template.
    // -------------------------------------------------------------------------

    /** Configuration key holding a comma-separated list of pinned product ids. */
    const CONFIG_SHOWCASE_PRODUCTS = 'MZCPF_SHOWCASE_PRODUCTS';

    /** How many products the home-page pattern switcher offers at most. */
    const SHOWCASE_LIMIT = 4;

    public function renderWidget($hookName = null, array $configuration = [])
    {
        $variables = $this->getWidgetVariables($hookName, $configuration);

        if (empty($variables['mzc_showcases'])) {
            return '';
        }

        $this->smarty->assign($variables);

        return $this->fetch('module:mzcproductfields/views/templates/hook/home_preview.tpl');
    }

    public function getWidgetVariables($hookName = null, array $configuration = [])
    {
        $idLang = (int) $this->context->language->id;
        $showcases = [];
        $fonts = [];

        foreach ($this->resolveShowcaseCandidates($configuration) as $idProduct) {
            if (count($showcases) >= self::SHOWCASE_LIMIT) {
                break;
            }

            $presented = $this->presentProductFields($idProduct);

            // Only the first free-text field is offered here. The point is one
            // obvious "type your dedication" box, not a second copy of the
            // product page's full field set.
            $field = null;
            foreach ($presented['fields'] as $candidate) {
                if (in_array($candidate['type'], [MzcProductFieldsField::TYPE_TEXT, MzcProductFieldsField::TYPE_TEXTAREA], true)) {
                    $field = $candidate;
                    break;
                }
            }

            if ($field === null) {
                continue;
            }

            $product = new Product($idProduct, false, $idLang);
            if (!Validate::isLoadedObject($product) || !$product->active) {
                continue;
            }

            $cover = Image::getCover($idProduct);
            if (!$cover) {
                continue;
            }

            $showcases[] = [
                'id_product' => $idProduct,
                'name' => $product->name,
                'url' => $this->context->link->getProductLink($product),
                // Tools::displayPrice() is gone on PS9 — same locale formatting
                // ProductFieldsPresenter::formatPrice() uses.
                'price' => $this->context->getCurrentLocale()->formatPrice(
                    $product->getPrice(true),
                    $this->context->currency->iso_code
                ),
                'image' => $this->context->link->getImageLink($product->link_rewrite, (int) $cover['id_image'], ImageType::getFormattedName('large')),
                'thumb' => $this->context->link->getImageLink($product->link_rewrite, (int) $cover['id_image'], ImageType::getFormattedName('small')),
                'field' => $field,
                'combination_rules' => $presented['combination_rules'],
            ];

            // Merged so the template emits one @font-face per family even when
            // several showcase products share the same preview font.
            foreach ($presented['fonts'] as $font) {
                $fonts[$font['font_family']] = $font;
            }
        }

        return [
            'mzc_showcases' => $showcases,
            'mzc_fonts' => array_values($fonts),
        ];
    }

    /**
     * Which products the home-page showcase offers, in order.
     *
     * Priority: an explicit `id_products` widget parameter, then the
     * MZCPF_SHOWCASE_PRODUCTS pin, then — and this is the path a shop
     * normally runs on — whatever the merchant already curates as the Home
     * category, in the position order they set there. That is the same source
     * ps_featuredproducts uses, so the showcase follows the home page without
     * anyone having to maintain a second list.
     *
     * Returns candidates, not the final list: a candidate still has to have a
     * free-text field and a cover image, so the SHOWCASE_LIMIT cap is applied
     * by the callers once they know which ones actually qualify.
     *
     * @return array<int,int>
     */
    private function resolveShowcaseCandidates(array $configuration = []): array
    {
        $candidates = [];

        if (!empty($configuration['id_products'])) {
            $candidates = array_map('intval', (array) $configuration['id_products']);
        } else {
            $pinned = (string) Configuration::get(self::CONFIG_SHOWCASE_PRODUCTS);
            foreach (explode(',', $pinned) as $raw) {
                $id = (int) trim($raw);
                if ($id > 0) {
                    $candidates[] = $id;
                }
            }
        }

        if (!$candidates) {
            $idLang = (int) $this->context->language->id;
            $home = new Category((int) Configuration::get('PS_HOME_CATEGORY'), $idLang);
            foreach ($home->getProducts($idLang, 1, 30, 'position', 'asc') ?: [] as $row) {
                $candidates[] = (int) $row['id_product'];
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * Whether the current request is the home page AND has something to show —
     * consulted from hookDisplayHeader, which must decide about assets long
     * before renderWidget() runs.
     */
    private function isHomePageWithShowcase(): bool
    {
        // php_self rather than `instanceof IndexController`: it is what the
        // controller itself reports and does not depend on the class being
        // resolvable through the override alias.
        if (!isset($this->context->controller->php_self) || $this->context->controller->php_self !== 'index') {
            return false;
        }

        foreach ($this->resolveShowcaseCandidates() as $idProduct) {
            foreach ($this->presentProductFields($idProduct)['fields'] as $field) {
                if (in_array($field['type'], [MzcProductFieldsField::TYPE_TEXT, MzcProductFieldsField::TYPE_TEXTAREA], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Backoffice order page — shows the JPG snapshot(s) captured client-side
    // at content-confirmation time (see views/js/front-snapshot.js), so a
    // merchant can see exactly what the customer's personalization looked
    // like without reopening the product with the same combination selected.
    // Identical hook/params shape on PS 8.2.7 and 9.0.2 (confirmed: both
    // render `{{ renderhook('displayAdminOrderMain', {id_order: ...}) }}` in
    // the same core Sell/Order/Order/view.html.twig template) — only
    // `id_order` is passed, never a full Order object.
    // -------------------------------------------------------------------------

    public function hookDisplayAdminOrderMain(array $params): string
    {
        $idOrder = (int) ($params['id_order'] ?? 0);
        if ($idOrder <= 0) {
            return '';
        }

        $idLang = (int) $this->context->language->id;
        $snapshots = MzcProductFieldsOrderSnapshot::getForOrder($idOrder, $idLang);
        $textValues = MzcProductFieldsField::getTextValuesForOrder($idOrder, $idLang);
        if (!$snapshots && !$textValues) {
            return '';
        }

        foreach ($snapshots as &$snapshot) {
            $snapshot['url'] = MzcProductFieldsOrderSnapshot::getUrl($snapshot['filename']);
        }
        unset($snapshot);

        // Grouped by product here (rather than in the template) since Smarty
        // has no clean "group consecutive rows sharing a key" construct —
        // one products' worth of field:value pairs rendered together, in the
        // same order the fields were queried (product, then field id).
        $groupedTextValues = [];
        foreach ($textValues as $row) {
            $idProduct = (int) $row['id_product'];
            if (!isset($groupedTextValues[$idProduct])) {
                $groupedTextValues[$idProduct] = ['product_name' => $row['product_name'], 'values' => []];
            }
            $groupedTextValues[$idProduct]['values'][] = ['label' => $row['label'], 'value' => $row['value']];
        }
        $groupedTextValues = array_values($groupedTextValues);

        // Same reasoning as the text values above: with more than one
        // personalized product on the order, a flat grid of thumbnails gave
        // no way to tell which preview belongs to which product.
        $groupedSnapshots = [];
        foreach ($snapshots as $row) {
            $idProduct = (int) $row['id_product'];
            if (!isset($groupedSnapshots[$idProduct])) {
                $groupedSnapshots[$idProduct] = ['product_name' => $row['product_name'], 'items' => []];
            }
            $groupedSnapshots[$idProduct]['items'][] = $row;
        }
        $groupedSnapshots = array_values($groupedSnapshots);

        // Styled inline in the template rather than via
        // $this->context->controller->addCSS(): unlike the front-office hooks
        // above, this admin hook's controller is sometimes a legacy
        // controller and sometimes a Symfony one depending on PS version, and
        // that inconsistency isn't worth risking a fatal over for four rules
        // of CSS.
        $this->smarty->assign([
            'mzc_snapshots' => $groupedSnapshots,
            'mzc_text_values' => $groupedTextValues,
            // Translated here via the Symfony domain catalog (same as every
            // other admin-facing string in this module) rather than the
            // template's own {l} tag: {l} resolves through the OLD, separate
            // translations/{iso_code}.php catalog format, which would leave
            // this one string needing a different translation mechanism than
            // everything else — passing the resolved string in avoids that
            // split entirely.
            'mzc_snapshots_title' => $this->trans('Personalization preview', [], 'Modules.Mzcproductfields.Admin'),
            'mzc_text_values_title' => $this->trans('Personalization values', [], 'Modules.Mzcproductfields.Admin'),
        ]);

        return $this->fetch('module:mzcproductfields/views/templates/hook/order_snapshots.tpl');
    }

    /**
     * Shopping cart page — plain-text personalization values for whatever is
     * currently in the cart. Core's own classic theme already shows our
     * customizations natively (a "Product customization" link/modal per
     * line, via Product::getAllCustomizedDatas() — confirmed NOT filtered by
     * is_module, unlike the front-office product-page fields query), but a
     * customized/child theme's own cart template may not have kept that
     * block. This gives every theme a guaranteed, theme-independent way to
     * show it, same reasoning as the admin order page's text block above.
     */
    /**
     * Repairs a real, native PrestaShop core inconsistency around
     * customized cart lines — confirmed by direct reproduction, not
     * specific to this module's own fields: whenever a customizable
     * product is first added to a cart, `Cart::updateQty()` inserts its
     * `cart_product` row with `id_customization` left at 0 (core's own
     * "auto-link the single quantity=0 customization placeholder" flow in
     * `_updateCustomizationQuantity()` never goes back and updates that
     * column). But every CUSTOMER-FACING action after that point —
     * `Cart::deleteProduct()`'s own `cart_product` DELETE, the cart page's
     * quantity +/- controls, and the theme's own delete link — all use the
     * REAL, non-zero `customization.id_customization` (surfaced via
     * `Product::getAllCustomizedDatas()` / `CartPresenter`, and even via
     * `Cart::getProducts()`'s own SQL, whose later `cu.id_customization`
     * SELECT silently overwrites the earlier `cp.id_customization` one in
     * the same result row). The mismatch means those actions' own
     * `cart_product` WHERE clauses never match the real row: deleting the
     * last unit of a personalized product removes its `customization`/
     * `customized_data` rows but leaves a "ghost" `cart_product` row behind
     * (quantity intact, looking stuck in the cart), and increasing its
     * quantity from the cart page inserts a second, duplicate line instead
     * of updating the existing one.
     *
     * Fixed here rather than by overriding `Cart::deleteProduct()`/
     * `updateQty()` directly: those are used by every cart operation
     * store-wide, so patching cart_product back into consistency after the
     * fact — via the hook core already fires on every cart save — is far
     * less invasive, and fixes native/other-module customizations too, not
     * just this module's own fields. Scoped to (product, attribute) pairs
     * with EXACTLY ONE customization row in this cart: PrestaShop's own
     * multi-distinct-customization-of-the-same-combination case is a rarer,
     * pre-existing ambiguity this deliberately leaves untouched rather than
     * guess at.
     */
    public function hookActionCartSave(array $params): void
    {
        $cart = $params['cart'] ?? null;
        if (!($cart instanceof Cart) || !$cart->id) {
            return;
        }

        // The LEFT JOIN + IS NULL guard below is required, not optional:
        // `cart_product`'s primary key includes id_customization, so if a
        // row already exists at the target (id_cart, id_product,
        // id_address_delivery, id_product_attribute, real_id_customization)
        // key — e.g. a stray leftover row from before this fix existed —
        // the plain UPDATE would hit a duplicate-key error and abort.
        // Skipping that pair here just leaves it exactly as unrepaired as
        // it already was; every other pair still gets fixed.
        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'cart_product` cp
             INNER JOIN (
                 SELECT `id_product`, `id_product_attribute`, MIN(`id_customization`) AS `real_id_customization`
                 FROM `' . _DB_PREFIX_ . 'customization`
                 WHERE `id_cart` = ' . (int) $cart->id . '
                 GROUP BY `id_product`, `id_product_attribute`
                 HAVING COUNT(*) = 1
             ) cu ON cu.`id_product` = cp.`id_product` AND cu.`id_product_attribute` = cp.`id_product_attribute`
             LEFT JOIN `' . _DB_PREFIX_ . 'cart_product` cp2
                 ON cp2.`id_cart` = cp.`id_cart`
                 AND cp2.`id_product` = cp.`id_product`
                 AND cp2.`id_address_delivery` = cp.`id_address_delivery`
                 AND cp2.`id_product_attribute` = cp.`id_product_attribute`
                 AND cp2.`id_customization` = cu.`real_id_customization`
             SET cp.`id_customization` = cu.`real_id_customization`
             WHERE cp.`id_cart` = ' . (int) $cart->id . '
             AND cp.`id_customization` = 0
             AND cp2.`id_customization` IS NULL'
        );
    }

    /**
     * Required as soon as `customized_data.id_module` is set on a row (see
     * CartFieldValueWriter::patchCustomizedDataPrice() — set purely to
     * attribute the price surcharge to us) — core's own
     * `Product::getAllCustomizedDatas()` treats a non-zero `id_module` on a
     * CUSTOMIZE_TEXTFIELD row as "this module owns rendering this value" and
     * dispatches `Hook::exec('displayCustomization', ['customization' =>
     * $row], $id_module)`, REPLACING `$row['value']` with whatever comes
     * back — confirmed as the source of a real, shipped crash
     * (`OrderProductCustomizationForViewing::__construct(): Argument #3
     * ($value) must be of type string, null given`) on the admin order
     * detail page: `displayCustomization` had never been registered/created
     * as a hook on this install (no other module here happens to use it),
     * so `Hook::exec()` short-circuits at its very first guard and returns
     * `null` outright — before it even gets to checking which modules are
     * registered. Two earlier fixes this session (making our products
     * genuinely `customizable` and correctly `in_cart`) are what actually
     * exposed this: our rows were previously invisible to core's own
     * customization-rendering paths entirely, so this dispatch was never
     * reached before now. The fix is simply to hand the original value
     * straight back, exactly reproducing what core would have shown had
     * `id_module` been left at 0.
     */
    public function hookDisplayCustomization(array $params): string
    {
        return (string) ($params['customization']['value'] ?? '');
    }

    public function hookDisplayShoppingCartFooter(): string
    {
        $idCart = (int) $this->context->cart->id;
        if ($idCart <= 0) {
            return '';
        }

        $idLang = (int) $this->context->language->id;
        $textValues = MzcProductFieldsField::getTextValuesForCart($idCart, $idLang);
        if (!$textValues) {
            return '';
        }

        $groupedTextValues = [];
        foreach ($textValues as $row) {
            $idProduct = (int) $row['id_product'];
            if (!isset($groupedTextValues[$idProduct])) {
                $groupedTextValues[$idProduct] = ['product_name' => $row['product_name'], 'values' => []];
            }
            $groupedTextValues[$idProduct]['values'][] = ['label' => $row['label'], 'value' => $row['value']];
        }

        $this->smarty->assign([
            'mzc_cart_text_values' => array_values($groupedTextValues),
            'mzc_cart_text_values_title' => $this->trans('Your personalization', [], 'Modules.Mzcproductfields.Shop'),
        ]);

        return $this->fetch('module:mzcproductfields/views/templates/hook/cart_text_values.tpl');
    }

    /**
     * Order confirmation ("thank you") page — the same theme-independent
     * text summary as the cart page footer above, but for the just-placed
     * order rather than a still-live cart (see
     * MzcProductFieldsField::getTextValuesForOrder()). Confirmed a real gap:
     * the classic theme's own native "Product customization" per-line detail
     * doesn't appear on this page at all, so without this a customer placing
     * a personalized order never sees a written confirmation of what they
     * typed, only on the cart page they've already left behind.
     */
    public function hookDisplayOrderConfirmation(array $params): string
    {
        $order = $params['order'] ?? null;
        if (!($order instanceof Order) || !$order->id) {
            return '';
        }

        $idLang = (int) $this->context->language->id;
        $textValues = MzcProductFieldsField::getTextValuesForOrder((int) $order->id, $idLang);
        if (!$textValues) {
            return '';
        }

        $groupedTextValues = [];
        foreach ($textValues as $row) {
            $idProduct = (int) $row['id_product'];
            if (!isset($groupedTextValues[$idProduct])) {
                $groupedTextValues[$idProduct] = ['product_name' => $row['product_name'], 'values' => []];
            }
            $groupedTextValues[$idProduct]['values'][] = ['label' => $row['label'], 'value' => $row['value']];
        }

        $this->smarty->assign([
            'mzc_cart_text_values' => array_values($groupedTextValues),
            'mzc_cart_text_values_title' => $this->trans('Your personalization', [], 'Modules.Mzcproductfields.Shop'),
        ]);

        return $this->fetch('module:mzcproductfields/views/templates/hook/cart_text_values.tpl');
    }

    /**
     * UI strings for the content-confirmation modal (front-confirmation.js)
     * — translated here, not hardcoded in JS, since this ships on a
     * Polish-language shop.
     *
     * @return array<string,string>
     */
    private function getConfirmationTranslations(): array
    {
        return [
            'confirmTitle' => $this->trans('Please confirm your personalization', [], 'Modules.Mzcproductfields.Shop'),
            'edit' => $this->trans('Edit', [], 'Modules.Mzcproductfields.Shop'),
            'confirm' => $this->trans('Confirm and add to cart', [], 'Modules.Mzcproductfields.Shop'),
            'priceNote' => $this->trans('Personalization adds %price% to your order.', [], 'Modules.Mzcproductfields.Shop'),
            'genericError' => $this->trans('Something went wrong — please try again.', [], 'Modules.Mzcproductfields.Shop'),
        ];
    }
}
