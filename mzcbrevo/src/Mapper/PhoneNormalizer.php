<?php

declare(strict_types=1);

namespace Mzc\Brevo\Mapper;

/**
 * Phone numbers as Brevo's SMS attribute wants them: E.164 ("+48601234567").
 *
 * Brevo rejects the whole contact on a malformed SMS value, so anything that does
 * not come out clean is dropped rather than sent. Polish numbers are the common
 * case: nine digits without a prefix get +48.
 */
final class PhoneNormalizer
{
    /** Calling codes for the countries the shops actually ship to; anything else must carry its own "+". */
    public const CALLING_CODES = [
        'PL' => '48', 'DE' => '49', 'CZ' => '420', 'SK' => '421', 'GB' => '44', 'IE' => '353',
        'NL' => '31', 'BE' => '32', 'FR' => '33', 'IT' => '39', 'ES' => '34', 'AT' => '43',
        'LT' => '370', 'UA' => '380', 'NO' => '47', 'SE' => '46', 'DK' => '45', 'US' => '1',
    ];

    public static function toE164(string $phone, string $countryIso = 'PL'): ?string
    {
        $phone = trim($phone);

        if ($phone === '') {
            return null;
        }

        $plus = strpos($phone, '+') === 0;
        $digits = (string) preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return null;
        }

        if (!$plus && strpos($digits, '00') === 0) {
            $plus = true;
            $digits = substr($digits, 2);
        }

        if (!$plus) {
            $code = self::CALLING_CODES[strtoupper($countryIso)] ?? null;

            if ($code === null) {
                return null;
            }

            $digits = ltrim($digits, '0');

            // A Polish number typed with its prefix but without "+": 48601234567.
            if (!(strlen($digits) > 9 && strpos($digits, $code) === 0)) {
                $digits = $code . $digits;
            }
        }

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        // Poland: exactly 9 national digits.
        if (strpos($digits, '48') === 0 && strlen($digits) !== 11) {
            return null;
        }

        return '+' . $digits;
    }
}
