{*
 * MZC Landing Newsletter
 *
 * @author    Marcin Zbijowski Consulting
 * @copyright 2026 Marcin Zbijowski Consulting
 * @license   MIT
 *}
<!DOCTYPE html>
<html lang="{$language.iso_code|escape:'htmlall':'UTF-8'|default:'en'}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$meta_title|escape:'html':'UTF-8'}</title>
    {if $meta_description}
        <meta name="description" content="{$meta_description|escape:'html':'UTF-8'}">
    {/if}
    {if $meta_keywords}
        <meta name="keywords" content="{$meta_keywords|escape:'html':'UTF-8'}">
    {/if}
    {* Render displayHeader hook — enables GTM, GA, and other tracking scripts *}
    {hook h='displayHeader'}
    {* Module CSS loaded AFTER theme CSS so it wins the cascade *}
    <link rel="stylesheet" href="{$css_url|escape:'html':'UTF-8'}">
    {if $custom_css}
        <div id="mzc-custom-css-data" data-css="{$custom_css|escape:'html':'UTF-8'}" style="display:none"></div>
        <script>
        (function() {
            var el = document.getElementById('mzc-custom-css-data');
            if (el && el.dataset.css) {
                var s = document.createElement('style');
                s.textContent = el.dataset.css;
                document.head.appendChild(s);
            }
        })();
        </script>
    {/if}
</head>
<body>
    {* Render displayAfterBodyOpeningTag hook — enables GTM noscript fallback *}
    {hook h='displayAfterBodyOpeningTag'}

    <div class="mzc-landing-container">
        <div class="mzc-landing-content">

            {* Store logo *}
            {if $shop_logo}
                <div class="mzc-landing-logo">
                    <img src="{$shop_logo|escape:'html':'UTF-8'}"
                         alt="{$shop_name|escape:'html':'UTF-8'}">
                </div>
            {/if}

            {* Custom message from admin (base64-encoded to bypass Smarty escape_html) *}
            <div class="mzc-landing-message" id="mzc-landing-message"></div>
            <div id="mzc-landing-message-data" data-b64="{$landing_message_b64|escape:'html':'UTF-8'}" style="display:none"></div>
            <script>
            (function() {
                var src = document.getElementById('mzc-landing-message-data');
                var dst = document.getElementById('mzc-landing-message');
                if (src && dst && src.dataset.b64) {
                    dst.innerHTML = atob(src.dataset.b64);
                }
            })();
            </script>

            {* Newsletter signup form *}
            <div class="mzc-landing-form-wrapper">
                <form id="mzc-newsletter-form" class="mzc-landing-form"
                      method="post" action="{$subscribe_url|escape:'html':'UTF-8'}">
                    <input type="hidden" name="token" value="{$token|escape:'html':'UTF-8'}">

                    <div class="mzc-form-group">
                        <input type="email"
                               name="email"
                               id="mzc-email"
                               class="mzc-form-input"
                               placeholder="{l s='Enter your email address' mod='mzclandingnewsletter'}"
                               required
                               autocomplete="email">
                        <button type="submit" class="mzc-form-button" id="mzc-submit-btn">
                            {l s='Subscribe' mod='mzclandingnewsletter'}
                        </button>
                    </div>

                    {* GDPR consent checkbox rendered by psgdpr module *}
                    <div class="mzc-gdpr-consent"></div>
                    {if $gdpr_consent}
                        <div id="mzc-gdpr-data" data-html="{$gdpr_consent|escape:'html':'UTF-8'}" style="display:none"></div>
                    {/if}

                    {* Inject GDPR HTML + fix psgdpr custom checkbox: hide Material Icons, restore native checkbox *}
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var root = document.querySelector('.mzc-gdpr-consent');
                        if (!root) return;
                        // Inject GDPR consent HTML from data attribute (avoids nofilter in Smarty)
                        var gdprData = document.getElementById('mzc-gdpr-data');
                        if (gdprData && gdprData.dataset.html) {
                            root.innerHTML = gdprData.dataset.html;
                        }
                        // Hide Material Icons (font not loaded on standalone page)
                        root.querySelectorAll('.material-icons, .psgdpr_consent_icon').forEach(function(el) {
                            el.style.display = 'none';
                        });
                        // Restore native checkbox (psgdpr sets opacity:0 + position:absolute)
                        root.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
                            cb.style.cssText = 'appearance:checkbox!important;-webkit-appearance:checkbox!important;opacity:1!important;position:static!important;pointer-events:auto!important;width:16px!important;height:16px!important;margin:0!important;flex-shrink:0!important;cursor:pointer';
                        });
                        // The label wraps checkbox + icon-span + text-span — make it a flex row
                        root.querySelectorAll('label').forEach(function(lbl) {
                            lbl.style.cssText = 'display:flex!important;flex-direction:row!important;flex-wrap:nowrap!important;align-items:center!important;gap:8px!important;padding:0!important;margin:0!important;cursor:pointer';
                            // Hide icon span (first span), show text span (last span)
                            var spans = lbl.querySelectorAll(':scope > span');
                            if (spans.length >= 2) {
                                spans[0].style.display = 'none';
                                spans[spans.length - 1].style.cssText = 'display:inline!important;flex:1 1 auto!important';
                            }
                        });
                    });
                    </script>
                </form>

                {* Feedback area for success / error messages *}
                <div id="mzc-form-feedback" class="mzc-form-feedback" style="display:none;"></div>
            </div>

        </div>
    </div>

    {* Translation bridge for external JS *}
    <script>
    window.mzcLandingConfig = {
        strings: {
            subscribe: '{l s='Subscribe' mod='mzclandingnewsletter' js=1}',
            subscribing: '{l s='Subscribing...' mod='mzclandingnewsletter' js=1}',
            gdprError: '{l s='Please accept the privacy policy to continue.' mod='mzclandingnewsletter' js=1}',
            genericError: '{l s='An error occurred. Please try again.' mod='mzclandingnewsletter' js=1}'
        }
    };
    </script>
    <script src="{$js_url|escape:'html':'UTF-8'}"></script>

    {* Render displayBeforeBodyClosingTag hook *}
    {hook h='displayBeforeBodyClosingTag'}
</body>
</html>
