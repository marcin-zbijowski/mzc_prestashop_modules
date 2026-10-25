<?php

declare(strict_types=1);

namespace Mzc\Brevo\Api;

/**
 * json_encode() with floats written the short way.
 *
 * json_encode() honours the serialize_precision ini setting, and some PHP images
 * (the official PrestaShop one among them) set it to 17: 27.3 then goes out as
 * 27.300000000000001. Brevo would store that as an order amount. -1 means "the
 * shortest representation that reads back as the same float".
 */
final class Json
{
    public const FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;

    public static function encode($value, int $flags = 0): string
    {
        $previous = ini_get('serialize_precision');
        ini_set('serialize_precision', '-1');

        try {
            return (string) json_encode($value, self::FLAGS | $flags);
        } finally {
            ini_set('serialize_precision', (string) $previous);
        }
    }
}
