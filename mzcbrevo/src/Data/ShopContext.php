<?php

declare(strict_types=1);

namespace Mzc\Brevo\Data;

use Cart;
use Configuration;
use Context;
use Country;
use Currency;
use Language;
use Link;
use Shop;

/**
 * Puts the legacy Context in the state a front request would have, for one shop.
 *
 * The worker runs from `bin/console`, where config.inc.php sets up a Context with a
 * shop and little else. Prices (Cart::getProducts()), links and image URLs all read
 * from it, so before sending anything for a shop the worker switches here: that
 * shop, its default language, currency and country, and a Link object that builds
 * https URLs — in the CLI there is no request to tell it the protocol — and an
 * empty visitor cart.
 */
final class ShopContext
{
    public static function switchTo(int $idShop): void
    {
        $context = Context::getContext();
        $shop = new Shop($idShop);

        if (!\Validate::isLoadedObject($shop)) {
            return;
        }

        Shop::setContext(Shop::CONTEXT_SHOP, $idShop);
        $context->shop = $shop;

        $idLang = (int) Configuration::get('PS_LANG_DEFAULT', null, null, $idShop);
        $context->language = new Language($idLang);

        $idCurrency = (int) Configuration::get('PS_CURRENCY_DEFAULT', null, null, $idShop);
        $context->currency = new Currency($idCurrency);

        $idCountry = (int) Configuration::get('PS_COUNTRY_DEFAULT', null, null, $idShop);
        $context->country = new Country($idCountry);

        $ssl = (bool) Configuration::get('PS_SSL_ENABLED', null, null, $idShop);
        $protocol = $ssl ? 'https://' : 'http://';
        $context->link = new Link($protocol, $protocol);

        // Product::getPriceStatic() refuses to run without a cart (or an employee) in
        // the context. A visitor's empty cart is what a front page would have.
        if (!isset($context->cart) || !is_object($context->cart) || (int) $context->cart->id_shop !== $idShop) {
            $cart = new Cart();
            $cart->id_shop = $idShop;
            $cart->id_lang = $idLang;
            $cart->id_currency = $idCurrency;
            $context->cart = $cart;
        }
    }
}
