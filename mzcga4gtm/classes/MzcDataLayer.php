<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Renders inline dataLayer script tags.
 * Generates HTML directly — no Smarty template needed for trivial script output.
 *
 * GA4 ecommerce structure (required by GTM GA4 ecommerce variables):
 *
 *   dataLayer.push({ ecommerce: null });   ← clears previous ecommerce data
 *   dataLayer.push({
 *     event:     "purchase",
 *     ecommerce: { transaction_id, value, currency, items, ... }
 *   });
 *
 * renderInit()           — non-ecommerce page context, no nesting needed
 * renderPush()           — all ecommerce events (auto-wraps under 'ecommerce')
 * renderPushWithDedup()  — purchase only, same wrapping + localStorage guard
 */
class MzcDataLayer
{
    /**
     * Renders the dataLayer initialization block + page context push.
     * Must be called once in <head> before any other pushes.
     * Not an ecommerce event — data is NOT nested under 'ecommerce'.
     *
     * @param string $currencyIso  ISO 4217 currency code (e.g. "PLN")
     * @param string $languageIso  ISO 639-1 language code (e.g. "pl")
     * @return string
     */
    public static function renderInit(string $currencyIso, string $languageIso): string
    {
        $contextPayload = json_encode(
            [
                'event'         => 'mzc_page_context',
                'shop_currency' => $currencyIso,
                'shop_language' => $languageIso,
            ],
            JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $langPrefixJson = json_encode(strtoupper($languageIso), JSON_HEX_TAG);

        return '<script>' . "\n"
            . 'window.dataLayer = window.dataLayer || [];' . "\n"
            . 'window.dataLayer.push(' . $contextPayload . ');' . "\n"
            . 'window.mzc_ga4_lang_prefix=' . $langPrefixJson . ';' . "\n"
            . '</script>' . "\n";
    }

    /**
     * Renders an ecommerce dataLayer.push() wrapped in the GA4 ecommerce structure.
     *
     * The 'event' key stays at the top level.
     * All other fields are moved inside 'ecommerce: {}' as required by GA4 / GTM.
     * A preceding push of {ecommerce: null} clears any previous ecommerce data.
     *
     * Input payload example:
     *   ['event' => 'view_item', 'currency' => 'PLN', 'value' => 149, 'items' => [...]]
     *
     * Output dataLayer push:
     *   { event: "view_item", ecommerce: { currency: "PLN", value: 149, items: [...] } }
     *
     * @param array $payload Must contain 'event' key; all other fields go into ecommerce
     * @return string
     */
    public static function renderPush(array $payload): string
    {
        $eventName = (string) ($payload['event'] ?? '');
        unset($payload['event']);

        $eventJson     = json_encode($eventName, JSON_HEX_TAG);
        $ecommerceJson = json_encode(
            $payload,
            JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return '<script>' . "\n"
            . 'window.dataLayer = window.dataLayer || [];' . "\n"
            . 'window.dataLayer.push({ecommerce:null});' . "\n"
            . 'window.dataLayer.push({event:' . $eventJson . ',ecommerce:' . $ecommerceJson . '});' . "\n"
            . '</script>' . "\n";
    }

    /**
     * Renders a purchase ecommerce push wrapped in a localStorage deduplication guard.
     * Used exclusively for the 'purchase' event to prevent duplicate fires on page reload.
     *
     * The payload must contain '_dedup_key' (e.g. "mzc_ga4_purch_123").
     * This field is stripped before the push so it never appears in GTM.
     *
     * @param array $payload Must contain 'event' and '_dedup_key'; all other fields → ecommerce
     * @return string
     */
    public static function renderPushWithDedup(array $payload): string
    {
        $dedupKey  = (string) ($payload['_dedup_key'] ?? '');
        $eventName = (string) ($payload['event'] ?? 'purchase');
        unset($payload['_dedup_key'], $payload['event']);

        $eventJson     = json_encode($eventName, JSON_HEX_TAG);
        $ecommerceJson = json_encode(
            $payload,
            JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $keyJson = json_encode($dedupKey, JSON_HEX_TAG);

        return '<script>' . "\n"
            . '(function () {' . "\n"
            . '  var key = ' . $keyJson . ';' . "\n"
            . '  window.dataLayer = window.dataLayer || [];' . "\n"
            . '  if (!window.localStorage.getItem(key)) {' . "\n"
            . '    window.dataLayer.push({ecommerce:null});' . "\n"
            . '    window.dataLayer.push({event:' . $eventJson . ',ecommerce:' . $ecommerceJson . '});' . "\n"
            . '    try { window.localStorage.setItem(key, \'1\'); } catch (e) {}' . "\n"
            . '  }' . "\n"
            . '}());' . "\n"
            . '</script>' . "\n";
    }
}
