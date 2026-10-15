# mzcga4gtm

The shop's one place for analytics. It loads the Google Tag Manager container and
pushes the GA4 e-commerce events to `window.dataLayer`; GTM routes them to GA4.
It replaces a hand-edited `ps_googleanalytics`, which has to be switched off.

PrestaShop 8 and 9. Developed and tested on PrestaShop 9.2.0 / PHP 8.5.

## Settings

*Modules → Module Manager → MZC GA4 / GTM DataLayer → Configure*

| Setting | Configuration key | Meaning |
|---|---|---|
| GTM container ID | `MZC_GTM_ID` | `GTM-XXXXXXX`; trimmed and upper-cased on save; anything that is not `GTM-` plus 4–10 letters/digits is refused. **Empty means "load nothing".** |
| Load GTM | `MZC_GTM_ENABLED` | The container loads only when this is on **and** the ID is valid. |

A fresh install, and an upgrade from 1.0.0, leave the ID empty and the switch off:
nothing changes on a page until someone types the ID in. That is deliberate — a
staging copy must not report to the production container.

The panel warns, and never blocks, when:

- the ID is the production container (`GTM-5HCL8MXQ`) and the shop is not on
  `artedeum.pl` (`Mzcga4gtm::PRODUCTION_GTM_ID` / `PRODUCTION_HOST`);
- loading is on but the ID is empty;
- the consent module (`megacookies`) is not active;
- the consent defaults would run after GTM starts (see below);
- `ps_googleanalytics` is still active.

## What it prints

| Hook | Output |
|---|---|
| `displayHeader` | `dataLayer` + `mzc_page_context`, then the GTM loader (only when configured, on, and not an administrator's preview — a URL with `adtoken` or `live_edit`) |
| `displayAfterBodyOpeningTag` | the `<noscript>` iframe, same conditions |
| `displayBeforeBodyClosingTag` | `view_item`, `view_item_list`, `view_cart`, `begin_checkout` and the cart/checkout scripts (unchanged) |
| `displayOrderConfirmation` | `purchase` (unchanged structure) |

The loader is Google's standard snippet, untouched. It adds the `gtm.js` event
itself, so **no `dataLayer.push({'gtm.start':…,event:'gtm.js'})` is made by hand**;
doing both starts GTM twice. `gtag()` is defined if nothing else has defined it.

The back office never gets any of it: those are front hooks.

## Consent

Consent Mode is **not** handled here. `megacookies` pushes
`gtag('consent','default',…)`, `url_passthrough` and `ads_data_redaction` from an
inline script in its `displayHeader` output, and updates the consent from its worker
script. This module neither sets `url_passthrough` / `ads_data_redaction` nor
repeats the default.

What it does have to guarantee is the **order**: the default must be in `dataLayer`
before the `gtm.js` event. That is not automatic on PrestaShop 9:

- `megacookies` registers on the old hook name `header`. On PrestaShop 9 that is a
  hook row of its own, and `Hook::getHookModuleExecList()` runs modules registered
  on an alias *after* every module on `displayHeader` itself — whatever their
  positions. With GTM in this module's `displayHeader` output, `gtm.js` would come
  first (measured on 9.2.0: `mzc_page_context > gtm.js > consent default`).
- `ensureHeaderOrder()` therefore moves `megacookies` from the alias row to
  `displayHeader` (its `hookHeader()` is still called, through the retro hook
  name), then puts this module right after it. Measured result:
  `consent default > … > mzc_page_context > gtm.js > consent update`.

It runs on install, on upgrade to 1.1.0 and on every Save in the panel, so a
reinstalled `megacookies` or a manual drag in *Design → Positions* is repaired by
saving the settings. The panel says when a repair is needed.

When `megacookies` is **not** installed or enabled, the loader pushes a denied
`consent default` first (only if `dataLayer` has none yet), so GTM never starts
without one. Nothing then grants consent: the panel warns about it.

## Events

Names and structure are unchanged from 1.0.0: `mzc_page_context`, `view_item`,
`view_item_list`, `add_to_cart` / `remove_from_cart` (from `mzc_cart.js`, no page
reload), `view_cart`, `begin_checkout`, `add_shipping_info`, `add_payment_info`,
`purchase`. E-commerce events are `{event, ecommerce:{…}}` preceded by
`{ecommerce:null}`.

### `purchase` is sent once

`purchase` is pushed from `displayOrderConfirmation` and guarded by a
`localStorage` flag per order (`mzc_ga4_purch_<id_order>`, `transaction_id` is the
order id). Reloading the confirmation page pushes nothing.

In 1.1.0 every storage access is in `try/catch`, with `sessionStorage` as a
fallback. Before, an exception from a blocked `localStorage` was uncaught and the
purchase was lost altogether.

Limits worth knowing: the flag lives in the browser, so opening the confirmation
link in a *different* browser pushes `purchase` again (GA4 discards a repeated
`transaction_id`); with both storages blocked there is nothing to remember, and
every reload pushes it.

## Upgrading from 1.0.0

`upgrade/upgrade-1.1.0.php` registers `displayAfterBodyOpeningTag`, seeds the two
settings (empty / off) and runs `ensureHeaderOrder()`. It runs by itself when the
files of 1.1.0 are in place and the module is next loaded; or:

```bash
docker compose exec -u www-data prestashop php bin/console prestashop:module upgrade mzcga4gtm
```

Never run `bin/console` as root: it leaves `var/cache/prod` owned by root and the
front office returns 500.

## Translations

`translations/pl-PL/ModulesMzcga4gtmAdmin.pl-PL.xlf` (domain
`Modules.Mzcga4gtm.Admin`).

## Rolling out

1. Install the 1.1.0 files (`zip_module.sh mzcga4gtm`, upload in the back office, or copy
   the directory), upgrade, open the panel and press Save.
2. Switch `ps_googleanalytics` off — two analytics modules count every visit twice.
3. Enter the container ID and switch loading on **after** the cutover, on the
   production domain only. Staging stays empty.
