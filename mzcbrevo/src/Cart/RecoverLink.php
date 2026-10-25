<?php

declare(strict_types=1);

namespace Mzc\Brevo\Cart;

/**
 * The "back to your cart" link in abandoned-cart e-mails: the cart id signed with an
 * HMAC of the shop's cookie key, so a link cannot be forged for someone else's cart.
 *
 * Unlike PrestaShop's own recover_cart link, following it never logs anybody in: an
 * e-mail gets forwarded, and the link would hand over the account. See the recover
 * front controller for what it does instead.
 */
final class RecoverLink
{
    public static function token(int $idCart, string $secret): string
    {
        return substr(hash_hmac('sha256', 'mzcbrevo-recover:' . $idCart, $secret), 0, 32);
    }

    public static function isValid(int $idCart, string $token, string $secret): bool
    {
        return $idCart > 0 && $token !== '' && hash_equals(self::token($idCart, $secret), $token);
    }

    public static function url(int $idCart, int $idShop, int $idLang): string
    {
        return (string) \Context::getContext()->link->getModuleLink(
            'mzcbrevo',
            'recover',
            ['id_cart' => $idCart, 't' => self::token($idCart, _COOKIE_KEY_)],
            true,
            $idLang,
            $idShop
        );
    }
}
