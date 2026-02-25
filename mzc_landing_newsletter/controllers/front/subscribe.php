<?php
/**
 * MZC Landing Newsletter - Subscribe Front Controller
 *
 * Handles newsletter subscription AJAX requests from the landing page.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class Mzc_landing_newsletterSubscribeModuleFrontController extends ModuleFrontController
{
    /** @var bool */
    public $ssl = true;

    /**
     * Override to allow this controller to work even when
     * PrestaShop's built-in maintenance mode is enabled.
     */
    protected function displayMaintenancePage()
    {
        // No-op: the subscribe endpoint must remain accessible
    }

    /**
     * Handle the subscription POST request
     */
    public function postProcess()
    {
        // Only accept POST
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->ajaxResponse(false, $this->module->l('Invalid request method.', 'subscribe'));
        }

        // Validate CSRF token
        if (!$this->isLandingTokenValid()) {
            return $this->ajaxResponse(
                false,
                $this->module->l('Invalid security token. Please refresh the page and try again.', 'subscribe')
            );
        }

        // Validate email
        $email = trim(Tools::getValue('email'));
        if (!$email || !Validate::isEmail($email)) {
            return $this->ajaxResponse(
                false,
                $this->module->l('Please enter a valid email address.', 'subscribe')
            );
        }

        // Check rate limit (max 3 per IP per 10 minutes)
        if ($this->isRateLimited()) {
            return $this->ajaxResponse(
                false,
                $this->module->l('Too many attempts. Please try again in a few minutes.', 'subscribe')
            );
        }

        // Record rate-limit entry BEFORE duplicate check to prevent email enumeration.
        // Every valid attempt (well-formed email + valid token) counts toward the limit,
        // so an attacker cannot probe unlimited emails without hitting the rate wall.
        $this->recordRateLimitEntry();

        // Check for duplicate subscription
        // Return a generic message to prevent email enumeration (attacker cannot
        // distinguish between "new subscription" and "already exists")
        if ($this->isAlreadySubscribed($email)) {
            return $this->ajaxResponse(
                true,
                $this->module->l('Thank you for subscribing! We\'ll keep you updated.', 'subscribe')
            );
        }

        // Register the subscriber
        if ($this->registerSubscriber($email)) {
            return $this->ajaxResponse(
                true,
                $this->module->l('Thank you for subscribing! We\'ll keep you updated.', 'subscribe')
            );
        }

        return $this->ajaxResponse(
            false,
            $this->module->l('An error occurred. Please try again later.', 'subscribe')
        );
    }

    /**
     * Validate the CSRF token sent with the form
     */
    private function isLandingTokenValid()
    {
        $token = Tools::getValue('token');
        if (!$token) {
            return false;
        }

        // Accept token from current or previous hour to avoid edge-case rejections
        // when the token was generated just before the hour boundary
        /** @var Mzc_landing_newsletter $module */
        $module = $this->module;

        return hash_equals($module->generateToken(0), $token)
            || hash_equals($module->generateToken(-1), $token);
    }

    /**
     * Check if the current IP has exceeded the rate limit
     */
    private function isRateLimited()
    {
        $ip = Tools::getRemoteAddr();

        // Purge stale entries for this IP only (avoids full-table DELETE on every request)
        Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'mzc_landing_ratelimit`
                        WHERE `ip` = \'' . pSQL($ip) . '\'
                        AND `created_at` < DATE_SUB(NOW(), INTERVAL 10 MINUTE)'
        );

        // Probabilistic global cleanup (~1% of requests) to prevent table bloat
        if (mt_rand(1, 100) === 1) {
            Db::getInstance()->execute(
                'DELETE FROM `' . _DB_PREFIX_ . 'mzc_landing_ratelimit`
                                WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 10 MINUTE)'
            );
        }

        // Count recent attempts from this IP
        $count = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'mzc_landing_ratelimit`
                        WHERE `ip` = \'' . pSQL($ip) . '\'
                        AND `created_at` > DATE_SUB(NOW(), INTERVAL 10 MINUTE)'
        );

        return $count >= 3;
    }

    /**
     * Record a rate-limit entry for the current IP
     */
    private function recordRateLimitEntry()
    {
        $ip = Tools::getRemoteAddr();

        Db::getInstance()->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'mzc_landing_ratelimit` (`ip`, `created_at`)
                        VALUES (\'' . pSQL($ip) . '\', NOW())'
        );
    }

    /**
     * Check if the email is already subscribed
     * Checks both the emailsubscription table and the customer table
     */
    private function isAlreadySubscribed($email)
    {
        $idShop = (int) $this->context->shop->id;

        // Check emailsubscription table (guest subscribers)
        $guest = Db::getInstance()->getValue(
            'SELECT `id` FROM `' . _DB_PREFIX_ . 'emailsubscription`
                        WHERE `email` = \'' . pSQL($email) . '\'
                        AND `id_shop` = ' . $idShop
        );

        if ($guest) {
            return true;
        }

        // Check customer table (registered customers who opted in)
        $customer = Db::getInstance()->getValue(
            'SELECT `id_customer` FROM `' . _DB_PREFIX_ . 'customer`
                        WHERE `email` = \'' . pSQL($email) . '\'
                        AND `newsletter` = 1
                        AND `id_shop` = ' . $idShop
        );

        return (bool) $customer;
    }

    /**
     * Insert a new subscriber into the emailsubscription table
     */
    private function registerSubscriber($email)
    {
        $idShop = (int) $this->context->shop->id;
        $idShopGroup = (int) Shop::getContextShopGroupID();
        $idLang = (int) $this->context->language->id;
        $ip = pSQL(Tools::getRemoteAddr());

        return Db::getInstance()->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'emailsubscription`
                        (`id_shop`, `id_shop_group`, `email`, `newsletter_date_add`,
                            `ip_registration_newsletter`, `http_referer`, `active`, `id_lang`)
                        VALUES (
                                ' . $idShop . ',
                                ' . $idShopGroup . ',
                                \'' . pSQL($email) . '\',
                                NOW(),
                                \'' . $ip . '\',
                                \'mzc_landing_page\',
                                1,
                                ' . $idLang . '
                        )'
        );
    }

    /**
     * Send a JSON response and exit
     */
    private function ajaxResponse($success, $message)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => (bool) $success,
            'message' => $message,
        ]);
        exit;
    }
}
