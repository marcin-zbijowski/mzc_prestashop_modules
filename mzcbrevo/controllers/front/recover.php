<?php
/**
 * MZC Brevo — "back to your cart" from an abandoned-cart e-mail.
 *
 * The link carries the cart id and an HMAC (see Mzc\Brevo\Cart\RecoverLink). It never
 * logs anybody in — an e-mail gets forwarded, PrestaShop's own recover_cart link does
 * log in, and that is the difference:
 *
 *   - the visitor is signed in as the cart's owner: the cart itself is restored;
 *   - anybody else (signed out, another device, someone the e-mail was forwarded to):
 *     gets a copy of the cart's products, personalization included, in a cart of
 *     their own, and signs in or checks out as a guest from there.
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class MzcbrevoRecoverModuleFrontController extends ModuleFrontController
{
    public $auth = false;
    public $guestAllowed = true;

    public function postProcess(): void
    {
        $idCart = (int) Tools::getValue('id_cart');
        $token = (string) Tools::getValue('t');

        if (!\Mzc\Brevo\Cart\RecoverLink::isValid($idCart, $token, _COOKIE_KEY_)) {
            $this->fail($this->trans('This link is not valid.', [], 'Modules.Mzcbrevo.Shop'));
        }

        $cart = new Cart($idCart);

        if (!Validate::isLoadedObject($cart) || (int) $cart->id_shop !== (int) $this->context->shop->id) {
            $this->fail($this->trans('This cart has expired.', [], 'Shop.Notifications.Error'));
        }

        if ($cart->orderExists()) {
            $this->fail($this->trans('This cart was already used in an order and has expired.', [], 'Shop.Notifications.Error'));
        }

        $customer = $this->context->customer;
        $signedInOwner = $customer && $customer->isLogged() && (int) $customer->id === (int) $cart->id_customer;

        if ($signedInOwner) {
            $this->useCart((int) $cart->id);
        }

        $copy = $cart->duplicate();

        if (!$copy || empty($copy['success']) || !Validate::isLoadedObject($copy['cart'])) {
            $this->fail($this->trans('This cart has expired.', [], 'Shop.Notifications.Error'));
        }

        /** @var Cart $newCart */
        $newCart = $copy['cart'];
        $isLogged = $customer && $customer->isLogged();
        $newCart->id_customer = $isLogged ? (int) $customer->id : 0;
        $newCart->id_guest = (int) $this->context->cookie->id_guest;
        $newCart->id_address_delivery = $isLogged ? (int) Address::getFirstCustomerAddressId((int) $customer->id) : 0;
        $newCart->id_address_invoice = $newCart->id_address_delivery;
        $newCart->secure_key = $isLogged ? (string) $customer->secure_key : '';
        $newCart->id_lang = (int) $this->context->language->id;
        $newCart->id_currency = (int) $this->context->currency->id;
        $newCart->update();

        if ($newCart->id_address_delivery === 0) {
            Db::getInstance()->update('cart_product', ['id_address_delivery' => 0], '`id_cart` = ' . (int) $newCart->id);
        }

        $this->useCart((int) $newCart->id);
    }

    /**
     * @return never
     */
    private function useCart(int $idCart): void
    {
        $this->context->cookie->id_cart = $idCart;
        $this->context->cookie->write();

        Tools::redirect($this->context->link->getPageLink('cart', null, null, ['action' => 'show']));
        exit;
    }

    /**
     * @return never
     */
    private function fail(string $message): void
    {
        $this->errors[] = $message;
        $this->redirectWithNotifications($this->context->link->getPageLink('cart', null, null, ['action' => 'show']));
        exit;
    }
}
