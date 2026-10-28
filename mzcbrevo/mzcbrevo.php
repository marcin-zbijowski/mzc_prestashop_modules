<?php
/**
 * MZC Brevo
 *
 * Replaces the official Brevo plugin: contacts, orders and cart events go to Brevo
 * API v3, including what the official plugin cannot carry — personalization from
 * mzcproductfields and the occasion (christening, communion, wedding) a purchase was
 * for, as contact attributes for anniversary campaigns.
 *
 * Shop → queue → worker → Brevo. Hooks only write a row to the queue table; the
 * worker (`bin/console mzcbrevo:worker`, cron every minute) does the HTTP. Checkout
 * never waits for Brevo and nothing is lost when Brevo is down. No Brevo script is
 * ever loaded on the shop's pages: everything is server-side.
 *
 * The only traffic the other way is two webhooks (controllers/front/webhook.php):
 * unsubscribes and the results of batch imports.
 *
 * Transactional e-mails are not this module's business: those stay with Brevo SMTP
 * in PrestaShop and the mzcmailcontent templates.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

class Mzcbrevo extends Module
{
    public const TRANSLATION_DOMAIN = 'Modules.Mzcbrevo.Admin';

    public const HOOKS = [
        'actionCustomerAccountAdd',
        'actionObjectCustomerUpdateAfter',
        'actionObjectCustomerDeleteAfter',
        'actionNewsletterRegistrationAfter',
        'actionCartSave',
        'actionValidateOrder',
        'actionOrderStatusPostUpdate',
        'actionObjectProductAddAfter',
        'actionObjectProductUpdateAfter',
        'actionObjectProductDeleteAfter',
        'actionObjectCategoryAddAfter',
        'actionObjectCategoryUpdateAfter',
        'actionObjectCategoryDeleteAfter',
        'actionProductFormBuilderModifier',
        'actionAfterCreateProductFormHandler',
        'actionAfterUpdateProductFormHandler',
    ];

    /** Controllers of the checkout: an account created there is a "checkout" sign-up. */
    private const CHECKOUT_CONTROLLERS = ['order', 'orderopc', 'order-opc'];

    public function __construct()
    {
        $this->name = 'mzcbrevo';
        $this->tab = 'emailing';
        $this->version = '1.0.0';
        $this->author = 'Marcin Zbijowski Consulting';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => '9.99.99'];

        parent::__construct();

        $this->displayName = $this->trans('MZC Brevo', [], self::TRANSLATION_DOMAIN);
        $this->description = $this->trans(
            'Contacts, orders and cart events to Brevo, server-side through a queue. Replaces the official Brevo plugin.',
            [],
            self::TRANSLATION_DOMAIN
        );
        $this->confirmUninstall = $this->trans(
            'Uninstalling deletes the queue, including jobs not sent yet, and the manual occasions of products. Data already in Brevo stays there.',
            [],
            self::TRANSLATION_DOMAIN
        );
    }

    // =========================================================================
    // Install / uninstall
    // =========================================================================

    public function install(): bool
    {
        // Every stream starts off and there is no key: a fresh install, on staging
        // above all, sends nothing until someone has looked at the settings.
        return parent::install()
            && \Mzc\Brevo\Installer::installDb()
            && \Mzc\Brevo\Installer::installConfig()
            && $this->registerHook(self::HOOKS);
    }

    public function uninstall(): bool
    {
        return \Mzc\Brevo\Installer::uninstallDb()
            && \Mzc\Brevo\Installer::uninstallConfig()
            && parent::uninstall();
    }

    // =========================================================================
    // Back office
    // =========================================================================

    public function getContent(): string
    {
        return (new \Mzc\Brevo\Admin\ConfigurationPage($this))->render();
    }

    /**
     * Module::trans() is protected; the configuration page lives in its own class.
     */
    public function t(string $id, array $parameters = [], string $domain = self::TRANSLATION_DOMAIN): string
    {
        return $this->trans($id, $parameters, $domain);
    }

    // =========================================================================
    // Contacts and consent
    // =========================================================================

    /**
     * A new account: registration or checkout (guest or not). Joins the customers
     * list; with the newsletter box ticked, the newsletter list too.
     */
    public function hookActionCustomerAccountAdd(array $params): void
    {
        $customer = $params['newCustomer'] ?? null;

        if (!$customer instanceof Customer || !$customer->id) {
            return;
        }

        $this->safely(function () use ($customer): void {
            $source = (int) $customer->newsletter === 1 ? ($this->isCheckout() ? 'checkout' : 'rejestracja') : null;
            $this->enqueuer((int) $customer->id_shop)->contact((int) $customer->id, $source);
        });
    }

    /** Account edited, consent given or withdrawn, e-mail changed. */
    public function hookActionObjectCustomerUpdateAfter(array $params): void
    {
        $customer = $params['object'] ?? null;

        if (!$customer instanceof Customer || !$customer->id) {
            return;
        }

        $this->safely(function () use ($customer): void {
            $source = (int) $customer->newsletter === 1 && $this->isCheckout() ? 'checkout' : null;
            $this->enqueuer((int) $customer->id_shop)->contact((int) $customer->id, $source);
        });
    }

    /** Right to be forgotten: the contact is deleted in Brevo too. */
    public function hookActionObjectCustomerDeleteAfter(array $params): void
    {
        $customer = $params['object'] ?? null;

        if (!$customer instanceof Customer || (string) $customer->email === '') {
            return;
        }

        $this->safely(function () use ($customer): void {
            $this->enqueuer((int) $customer->id_shop)->deleteContact((int) $customer->id, (string) $customer->email);
        });
    }

    /**
     * The footer form of ps_emailsubscription. A sign-up goes through Brevo's double
     * opt-in; an unsubscribe takes the e-mail off the newsletter list.
     *
     * ps_emailsubscription's own verification e-mail should be off
     * (NW_VERIFICATION_EMAIL), or the customer confirms twice.
     */
    public function hookActionNewsletterRegistrationAfter(array $params): void
    {
        $email = trim((string) ($params['email'] ?? ''));

        if ($email === '' || !Validate::isEmail($email) || !empty($params['error'])) {
            return;
        }

        $this->safely(function () use ($params, $email): void {
            $enqueuer = $this->enqueuer((int) $this->context->shop->id);

            // ps_emailsubscription: 0 = subscribe, 1 = unsubscribe.
            if ((string) ($params['action'] ?? '0') === '1') {
                $enqueuer->unsubscribe($email);
            } else {
                $enqueuer->doubleOptIn($email, 'formularz');
            }
        });
    }

    // =========================================================================
    // Carts
    // =========================================================================

    /**
     * Fires on every cart change, often several times per request: one upsert of the
     * cart's row for the abandoned-cart detector and one queue row, deduplicated per
     * cart. Only carts that have a customer — a guest's cart gets one at the
     * "personal information" step of the checkout; before that there is nobody to
     * write to.
     */
    public function hookActionCartSave(array $params): void
    {
        $cart = $params['cart'] ?? null;

        if (!$cart instanceof Cart || !$cart->id || !(int) $cart->id_customer) {
            return;
        }

        if (!isset($this->context->controller) || !$this->context->controller instanceof FrontController) {
            return;
        }

        $this->safely(function () use ($cart): void {
            $idShop = (int) $cart->id_shop;
            $config = new \Mzc\Brevo\Config($idShop);

            if (!$config->isStreamEnabled(\Mzc\Brevo\Config::STREAM_CARTS)) {
                return;
            }

            \Mzc\Brevo\Cart\AbandonedCartDetector::touch((int) $cart->id, $idShop, (int) $cart->id_customer);
            (new \Mzc\Brevo\Queue\Enqueuer($config, $idShop))->cartUpdated((int) $cart->id);
        });
    }

    // =========================================================================
    // Orders
    // =========================================================================

    /** A placed order: the order, order_completed, and the customer's statistics. */
    public function hookActionValidateOrder(array $params): void
    {
        $order = $params['order'] ?? null;

        if (!$order instanceof Order || !$order->id) {
            return;
        }

        $this->safely(function () use ($order): void {
            $enqueuer = $this->enqueuer((int) $order->id_shop);
            $enqueuer->order((int) $order->id, \Mzc\Brevo\Mapper\EventMapper::ORDER_COMPLETED, (int) $order->current_state);
            $enqueuer->contact((int) $order->id_customer);
        });
    }

    /**
     * A state change: the order's new status in Brevo, order_status_changed, and the
     * customer's statistics (a payment or a cancellation changes TOTAL_SPENT).
     *
     * The order's first state is set inside validateOrder(), before
     * actionValidateOrder: that one is not a change, and order_completed covers it.
     */
    public function hookActionOrderStatusPostUpdate(array $params): void
    {
        $idOrder = (int) ($params['id_order'] ?? 0);
        $newState = $params['newOrderStatus'] ?? null;

        if ($idOrder <= 0 || !$newState instanceof OrderState) {
            return;
        }

        $this->safely(function () use ($idOrder, $newState, $params): void {
            $order = new Order($idOrder);

            if (!Validate::isLoadedObject($order)) {
                return;
            }

            $enqueuer = $this->enqueuer((int) $order->id_shop);

            if (!($params['oldOrderStatus'] ?? null) instanceof OrderState) {
                $enqueuer->contact((int) $order->id_customer);

                return;
            }

            $enqueuer->order($idOrder, \Mzc\Brevo\Mapper\EventMapper::ORDER_STATUS_CHANGED, (int) $newState->id);
            $enqueuer->contact((int) $order->id_customer);
        });
    }

    // =========================================================================
    // Catalogue
    // =========================================================================

    public function hookActionObjectProductAddAfter(array $params): void
    {
        $this->queueProduct($params['object'] ?? null, false);
    }

    public function hookActionObjectProductUpdateAfter(array $params): void
    {
        $this->queueProduct($params['object'] ?? null, false);
    }

    public function hookActionObjectProductDeleteAfter(array $params): void
    {
        $this->queueProduct($params['object'] ?? null, true);
    }

    public function hookActionObjectCategoryAddAfter(array $params): void
    {
        $this->queueCategory($params['object'] ?? null, false);
    }

    public function hookActionObjectCategoryUpdateAfter(array $params): void
    {
        $this->queueCategory($params['object'] ?? null, false);
    }

    public function hookActionObjectCategoryDeleteAfter(array $params): void
    {
        $this->queueCategory($params['object'] ?? null, true);
    }

    private function queueProduct($product, bool $deleted): void
    {
        if (!$product instanceof Product || !$product->id) {
            return;
        }

        $this->safely(function () use ($product, $deleted): void {
            foreach ($this->shopsInContext() as $idShop) {
                $this->enqueuer($idShop)->product((int) $product->id, $deleted);
            }
        });
    }

    private function queueCategory($category, bool $deleted): void
    {
        if (!$category instanceof Category || !$category->id) {
            return;
        }

        $this->safely(function () use ($category, $deleted): void {
            foreach ($this->shopsInContext() as $idShop) {
                $this->enqueuer($idShop)->category((int) $category->id, $deleted);
            }
        });
    }

    // =========================================================================
    // Product page: the "Occasion" choice (step 1 of the occasion detection)
    // =========================================================================

    /**
     * Adds a "Brevo" tab to the product page with one field: the occasion. Left on
     * "automatic", the category mapping and the product name decide (see
     * Occasion\OccasionDetector). The detected value is shown next to it.
     */
    public function hookActionProductFormBuilderModifier(array $params): void
    {
        $idProduct = (int) ($params['id'] ?? 0);

        if ($idProduct <= 0 || !isset($params['form_builder'])) {
            return;
        }

        $config = new \Mzc\Brevo\Config((int) $this->context->shop->id);
        $detected = (new \Mzc\Brevo\Occasion\OccasionExtractor($config->categoryOccasions()))->forProduct($idProduct);
        $manual = \Mzc\Brevo\Occasion\OccasionExtractor::getManual($idProduct);

        if ($manual === '' && $detected !== null) {
            $help = $this->t('Detected automatically: %occasion% (from the %source%).', [
                '%occasion%' => \Mzc\Brevo\Occasion\OccasionDetector::LABELS[$detected['occasion']],
                '%source%' => $this->sourceLabel($detected['source']),
            ]);
        } elseif ($manual === '') {
            $help = $this->t('Nothing detected automatically: this product sets no occasion on the customer\'s contact.');
        } else {
            $help = $this->t('Set by hand. Choose "Automatic" to let the category mapping and the product name decide.');
        }

        $this->get('form.form_builder_modifier')->addAfter(
            $params['form_builder'],
            'options',
            'mzcbrevo',
            \Mzc\Brevo\Form\ProductOccasionType::class,
            [
                'label' => $this->t('Brevo'),
                'data' => ['occasion' => $manual],
                'occasion_choices' => $this->occasionChoices(),
                'occasion_label' => $this->t('Occasion'),
                'occasion_help' => $help,
            ]
        );
    }

    public function hookActionAfterCreateProductFormHandler(array $params): void
    {
        $this->saveProductOccasion($params);
    }

    public function hookActionAfterUpdateProductFormHandler(array $params): void
    {
        $this->saveProductOccasion($params);
    }

    private function saveProductOccasion(array $params): void
    {
        $idProduct = (int) ($params['id'] ?? 0);
        $data = $params['form_data']['mzcbrevo'] ?? null;

        if ($idProduct <= 0 || !is_array($data)) {
            return;
        }

        \Mzc\Brevo\Occasion\OccasionExtractor::setManual($idProduct, (string) ($data['occasion'] ?? ''));
    }

    /**
     * @return array<string,string> label => value, for a Symfony ChoiceType
     */
    public function occasionChoices(): array
    {
        $choices = [$this->t('Automatic') => ''];

        foreach (\Mzc\Brevo\Occasion\OccasionDetector::LABELS as $code => $label) {
            $choices[$label] = $code;
        }

        $choices[$this->t('None (not an occasion product)')] = \Mzc\Brevo\Occasion\OccasionDetector::NONE;

        return $choices;
    }

    public function sourceLabel(string $source): string
    {
        switch ($source) {
            case \Mzc\Brevo\Occasion\OccasionDetector::SOURCE_MANUAL:
                return $this->t('manual choice');
            case \Mzc\Brevo\Occasion\OccasionDetector::SOURCE_CATEGORY:
                return $this->t('category mapping');
            default:
                return $this->t('product name');
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function enqueuer(int $idShop): \Mzc\Brevo\Queue\Enqueuer
    {
        $idShop = $idShop > 0 ? $idShop : (int) $this->context->shop->id;

        return new \Mzc\Brevo\Queue\Enqueuer(new \Mzc\Brevo\Config($idShop), $idShop);
    }

    /** @return int[] the shops a back-office change applies to */
    private function shopsInContext(): array
    {
        $ids = array_map('intval', Shop::getContextListShopID());

        return $ids ?: [(int) $this->context->shop->id];
    }

    private function isCheckout(): bool
    {
        $controller = $this->context->controller ?? null;

        return $controller instanceof FrontController
            && in_array((string) ($controller->php_self ?? ''), self::CHECKOUT_CONTROLLERS, true);
    }

    /**
     * Hooks run inside the customer's request — checkout above all. Whatever goes
     * wrong in here is logged and swallowed; it must never cost an order.
     */
    private function safely(callable $work): void
    {
        try {
            $work();
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog('mzcbrevo: ' . get_class($e) . ': ' . $e->getMessage(), 3, null, 'mzcbrevo', 0, true);
        }
    }
}
