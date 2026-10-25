<?php

declare(strict_types=1);

namespace Mzc\Brevo;

use Configuration;
use PhpEncryption;

/**
 * Every setting of the module, read for one shop.
 *
 * The keys live in PrestaShop's Configuration, per shop, so a second shop on the
 * same installation (harmonyposters.pl) can point at its own Brevo account, lists
 * and id prefixes without any code change. The API key is stored encrypted with
 * the shop's own cookie key; nothing else is secret enough to bother.
 */
final class Config
{
    public const API_KEY = 'MZCBREVO_API_KEY';
    public const LIST_NEWSLETTER = 'MZCBREVO_LIST_NEWSLETTER';
    public const LIST_CUSTOMERS = 'MZCBREVO_LIST_CUSTOMERS';
    public const DOI_TEMPLATE = 'MZCBREVO_DOI_TEMPLATE';
    public const DOI_REDIRECT = 'MZCBREVO_DOI_REDIRECT';
    public const DOI_ACCOUNT = 'MZCBREVO_DOI_ACCOUNT';
    public const STATUS_MAP = 'MZCBREVO_STATUS_MAP';
    public const CATEGORY_OCCASIONS = 'MZCBREVO_CATEGORY_OCCASIONS';
    public const ABANDON_MINUTES = 'MZCBREVO_ABANDON_MINUTES';
    public const ABANDON_MIN_TOTAL = 'MZCBREVO_ABANDON_MIN_TOTAL';
    public const CART_REQUIRE_CONSENT = 'MZCBREVO_CART_REQUIRE_CONSENT';
    public const STREAM_CONTACTS = 'MZCBREVO_STREAM_CONTACTS';
    public const STREAM_ORDERS = 'MZCBREVO_STREAM_ORDERS';
    public const STREAM_CARTS = 'MZCBREVO_STREAM_CARTS';
    public const STREAM_CATALOG = 'MZCBREVO_STREAM_CATALOG';
    public const WEBHOOK_SECRET = 'MZCBREVO_WEBHOOK_SECRET';
    public const EXT_ID_PREFIX = 'MZCBREVO_EXT_ID_PREFIX';
    public const ID_PREFIX = 'MZCBREVO_ID_PREFIX';
    public const PAUSED_UNTIL = 'MZCBREVO_PAUSED_UNTIL';

    /** Streams that can be switched on one by one, in the order the rollout plan enables them. */
    public const STREAMS = [
        self::STREAM_CONTACTS,
        self::STREAM_ORDERS,
        self::STREAM_CARTS,
        self::STREAM_CATALOG,
    ];

    /**
     * Values a fresh install starts with. Every stream is off: an install on staging
     * must not start sending anything before someone has entered a key and looked.
     */
    public const DEFAULTS = [
        self::API_KEY => '',
        self::LIST_NEWSLETTER => 0,
        self::LIST_CUSTOMERS => 0,
        self::DOI_TEMPLATE => 0,
        self::DOI_REDIRECT => '',
        self::DOI_ACCOUNT => 0,
        self::STATUS_MAP => '',
        self::CATEGORY_OCCASIONS => '',
        self::ABANDON_MINUTES => 60,
        self::ABANDON_MIN_TOTAL => 0,
        self::CART_REQUIRE_CONSENT => 1,
        self::STREAM_CONTACTS => 0,
        self::STREAM_ORDERS => 0,
        self::STREAM_CARTS => 0,
        self::STREAM_CATALOG => 0,
        self::EXT_ID_PREFIX => 'ps_',
        self::ID_PREFIX => '',
        self::PAUSED_UNTIL => 0,
    ];

    private ?int $idShop;

    public function __construct(?int $idShop = null)
    {
        $this->idShop = $idShop;
    }

    public function idShop(): ?int
    {
        return $this->idShop;
    }

    public function get(string $key): string
    {
        $value = Configuration::get($key, null, null, $this->idShop);

        return $value === false || $value === null ? (string) (self::DEFAULTS[$key] ?? '') : (string) $value;
    }

    public function getInt(string $key): int
    {
        return (int) $this->get($key);
    }

    public function getBool(string $key): bool
    {
        return (bool) $this->getInt($key);
    }

    public function set(string $key, $value): bool
    {
        return (bool) Configuration::updateValue($key, $value, false, null, $this->idShop);
    }

    public function apiKey(): string
    {
        $stored = $this->get(self::API_KEY);

        if ($stored === '') {
            return '';
        }

        $plain = (new PhpEncryption(_NEW_COOKIE_KEY_))->decrypt($stored);

        return is_string($plain) ? $plain : '';
    }

    public function setApiKey(string $plain): bool
    {
        $plain = trim($plain);

        return $this->set(self::API_KEY, $plain === '' ? '' : (new PhpEncryption(_NEW_COOKIE_KEY_))->encrypt($plain));
    }

    /**
     * Checked by the hooks on every cart save, so it looks at whether a key is
     * stored without decrypting it.
     */
    public function isStreamEnabled(string $stream): bool
    {
        return $this->getBool($stream) && $this->get(self::API_KEY) !== '';
    }

    /**
     * PrestaShop order state id => Brevo order status. An empty status means
     * "do not send orders in this state".
     *
     * @return array<int,string>
     */
    public function statusMap(): array
    {
        $map = json_decode($this->get(self::STATUS_MAP), true);

        return is_array($map) ? array_map('strval', $map) : [];
    }

    /**
     * @return array<int,string> id_category => occasion code
     */
    public function categoryOccasions(): array
    {
        $map = json_decode($this->get(self::CATEGORY_OCCASIONS), true);

        return is_array($map) ? array_map('strval', $map) : [];
    }

    public function extId(int $idCustomer): string
    {
        return $this->get(self::EXT_ID_PREFIX) . $idCustomer;
    }

    /** Product, category and order ids in Brevo: the shop's id with an optional per-shop prefix. */
    public function brevoId(int $id): string
    {
        return $this->get(self::ID_PREFIX) . $id;
    }
}
