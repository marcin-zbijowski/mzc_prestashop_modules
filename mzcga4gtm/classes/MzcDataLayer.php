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
 * renderGtmSnippet()     — the Google Tag Manager loader for <head>
 * renderGtmNoscript()    — the <noscript> iframe for after <body>
 */
class MzcDataLayer
{
    /**
     * Renders the Google Tag Manager container snippet for <head>.
     *
     * The loader is Google's standard one, untouched. It adds the `gtm.js` event to
     * dataLayer itself, so nothing here may push {'gtm.start': ..., event: 'gtm.js'}
     * by hand first — doing both makes GTM start twice.
     *
     * Before the loader, the script makes sure `dataLayer` and `gtag()` exist.
     * `gtag` is only defined when nothing else has: megacookies defines the same
     * one-liner, and the two are interchangeable.
     *
     * With $consentFallback, and only if dataLayer holds no `consent default` yet,
     * it also pushes a denied default. That is the case of no consent module being
     * active; with megacookies active it owns the default (its header script runs
     * first) and this block is not rendered at all. url_passthrough and
     * ads_data_redaction are deliberately not set — those are megacookies' call.
     *
     * @param string $gtmId           A container ID already checked against GTM_ID_PATTERN
     * @param bool   $consentFallback Push a denied Consent Mode default if none exists
     */
    public static function renderGtmSnippet(string $gtmId, bool $consentFallback): string
    {
        $idJs = json_encode($gtmId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $out = "<!-- Google Tag Manager -->\n<script>\n"
            . "window.dataLayer = window.dataLayer || [];\n"
            . "window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };\n";

        if ($consentFallback) {
            $out .= "(function () {\n"
                . "  var q = window.dataLayer, found = false;\n"
                . "  for (var i = 0; i < q.length; i++) {\n"
                . "    if (q[i] && q[i][0] === 'consent' && q[i][1] === 'default') { found = true; break; }\n"
                . "  }\n"
                . "  if (!found) {\n"
                . "    window.gtag('consent', 'default', {\n"
                . "      'ad_storage': 'denied', 'analytics_storage': 'denied',\n"
                . "      'functionality_storage': 'denied', 'personalization_storage': 'denied',\n"
                . "      'security_storage': 'denied', 'ad_personalization': 'denied',\n"
                . "      'ad_user_data': 'denied', 'wait_for_update': 500\n"
                . "    });\n"
                . "  }\n"
                . "}());\n";
        }

        $out .= "(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n"
            . "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n"
            . "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n"
            . "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n"
            . "})(window,document,'script','dataLayer'," . $idJs . ");\n"
            . "</script>\n<!-- End Google Tag Manager -->\n";

        return $out;
    }

    /**
     * Renders the <noscript> iframe for right after <body>.
     */
    public static function renderGtmNoscript(string $gtmId): string
    {
        $src = 'https://www.googletagmanager.com/ns.html?id=' . rawurlencode($gtmId);

        return "<!-- Google Tag Manager (noscript) -->\n"
            . '<noscript><iframe src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n"
            . "<!-- End Google Tag Manager (noscript) -->\n";
    }

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

        // The guard is a localStorage flag per order, set once the event has been pushed.
        // Storage can throw (blocked, private mode, quota), and an uncaught error here
        // used to swallow the purchase entirely: every read and write is wrapped, and
        // sessionStorage stands in when localStorage is unavailable.
        return '<script>' . "\n"
            . '(function () {' . "\n"
            . '  var key = ' . $keyJson . ';' . "\n"
            . '  function seen() {' . "\n"
            . '    try { return !!window.localStorage.getItem(key); } catch (e) {}' . "\n"
            . '    try { return !!window.sessionStorage.getItem(key); } catch (e) {}' . "\n"
            . '    return false;' . "\n"
            . '  }' . "\n"
            . '  function remember() {' . "\n"
            . '    try { window.localStorage.setItem(key, \'1\'); return; } catch (e) {}' . "\n"
            . '    try { window.sessionStorage.setItem(key, \'1\'); } catch (e) {}' . "\n"
            . '  }' . "\n"
            . '  if (seen()) { return; }' . "\n"
            . '  window.dataLayer = window.dataLayer || [];' . "\n"
            . '  window.dataLayer.push({ecommerce:null});' . "\n"
            . '  window.dataLayer.push({event:' . $eventJson . ',ecommerce:' . $ecommerceJson . '});' . "\n"
            . '  remember();' . "\n"
            . '}());' . "\n"
            . '</script>' . "\n";
    }
}
