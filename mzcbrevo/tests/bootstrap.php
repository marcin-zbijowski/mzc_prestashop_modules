<?php

/**
 * Unit tests run without PrestaShop. The classes under test are the pure ones
 * (mappers, occasion detection, retry policy, the HTTP client) plus the worker and
 * handlers against in-memory doubles; the few PrestaShop statics they reach are
 * stubbed here.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor-dev/autoload.php';

date_default_timezone_set('Europe/Warsaw');

define('_PS_VERSION_', '9.0.0');
define('_DB_PREFIX_', 'ps_');
define('_DB_NAME_', 'test');
define('_NEW_COOKIE_KEY_', 'test-key');
define('_COOKIE_KEY_', 'test-cookie-key');

if (!class_exists('Configuration')) {
    class Configuration
    {
        /** @var array<string,mixed> */
        public static array $values = [];

        public static function get($key, $idLang = null, $idShopGroup = null, $idShop = null, $default = false)
        {
            return self::$values[$key] ?? false;
        }

        public static function updateValue($key, $values, $html = false, $idShopGroup = null, $idShop = null)
        {
            self::$values[$key] = $values;

            return true;
        }
    }
}

if (!class_exists('PhpEncryption')) {
    class PhpEncryption
    {
        public function __construct($key)
        {
        }

        public function encrypt($plaintext)
        {
            return 'enc:' . $plaintext;
        }

        public function decrypt($cipherText)
        {
            return substr((string) $cipherText, 4);
        }
    }
}

if (!function_exists('pSQL')) {
    function pSQL($string, $htmlOK = false)
    {
        return addslashes((string) $string);
    }
}
