# MZC Landing Newsletter — Documentation

## Version 1.0.0

## Author: Marcin Zbijowski Consulting

## Compatibility: PrestaShop 8.0.0 – 9.x | PHP 8.1+

---

## Table of Contents

1. Overview
2. Requirements
3. Installation
4. Configuration
5. CSS Presets
6. Custom CSS Reference
7. GDPR Compliance
8. Google Tag Manager and Analytics
9. Multi-Store Setup
10. Subscriber Management
11. SEO Configuration
12. Interaction with Maintenance Mode
13. Admin Bypass
14. Rate Limiting
15. Security Features
16. Translations
17. Troubleshooting
18. Uninstallation
19. Support

---

## 1. Overview

MZC Landing Newsletter adds a Landing Page Mode to your PrestaShop store. When enabled, all front-office visitors see a branded landing page with your store logo, a custom message, and a newsletter signup form. Administrators and whitelisted IPs bypass the landing page and access the store normally.

This is independent from PrestaShop's built-in maintenance mode. Use it when your store is not ready yet, during a migration, catalog setup, rebranding, or any time you want to collect email subscribers before going live.

Subscribers are stored in PrestaShop's native newsletter table (ps_emailsubscription), so they appear automatically in your existing newsletter tools without any sync or export.

---

## 2. Requirements

- PrestaShop 8.0.0 or later (compatible up to 9.x)
- PHP 8.1 or later
- ps_emailsubscription module installed (ships with PrestaShop by default)
- psgdpr module installed and configured (optional, for GDPR consent checkbox)

---

## 3. Installation

### From PrestaShop Addons

1. Download the module ZIP file from your Addons account
2. Go to your PrestaShop back office
3. Navigate to Modules > Module Manager
4. Click Upload a module
5. Select the ZIP file and wait for installation to complete
6. Click Configure to set up the module

### Manual Installation

1. Extract the ZIP file
2. Upload the mzclandingnewsletter folder to your PrestaShop's modules directory via FTP
3. Go to Modules > Module Manager in the back office
4. Search for MZC Landing Newsletter
5. Click Install, then Configure

---

## 4. Configuration

Navigate to Modules > Module Manager, find MZC Landing Newsletter, and click Configure.

### Enable Landing Page Mode

Toggle the Enable Landing Page switch to Yes to activate the landing page for all visitors. Toggle to No to deactivate and show your normal store.

### Landing Page Message

Enter the message displayed on the landing page. This field supports:

- Rich text editing (bold, italic, links, formatting)
- Multilingual content — use the language selector to enter different messages per language
- HTML content — for advanced formatting

Default message: We're Coming Soon! Our store is under construction. Subscribe to our newsletter to be notified when we launch.

### Custom CSS

Enter custom CSS rules to change the appearance of the landing page. Leave empty to use the default styling. See Section 6 for the full list of available CSS classes.

---

## 5. CSS Presets

Three built-in presets are available in the CSS Presets panel on the configuration page. Click Load preset to populate the Custom CSS field with the preset styles.

### Modern Dark

Purple gradient background with a glassmorphism card effect. Features a violet-to-blue gradient subscribe button, translucent input fields, and an inverted logo for dark backgrounds. Best suited for tech, gaming, or modern lifestyle brands.

### Modern Light

Warm peach-to-white gradient background with a large rounded card and deep shadows. Orange gradient subscribe button with elegant typography and letter spacing. Best suited for fashion, beauty, or lifestyle brands.

### Soft Gray

Flat light gray background with a subtle bordered card. Muted gray tones throughout with an understated dark subscribe button. Minimal and elegant. Best suited for professional, B2B, or minimalist brands.

Each preset can be used as-is or modified further in the Custom CSS field after loading.

Important: Loading a preset replaces any existing custom CSS. If you have custom styles, copy them before loading a preset.

---

## 6. Custom CSS Reference

The following CSS classes are available for customization. A complete reference table with descriptions is displayed in the CSS Classes Reference panel on the configuration page.

### Layout Classes

- .mzc-landing-container — outermost wrapper, covers the full viewport, controls the background color or gradient
- .mzc-landing-content — the centered card or content box, controls max-width, padding, background, border-radius, and shadow

### Logo

- .mzc-landing-logo — the store logo wrapper div
- .mzc-landing-logo img — the logo image itself, controls max height and width

### Message

- .mzc-landing-message — wrapper div for the heading and paragraph text
- .mzc-landing-message h1 — the main heading
- .mzc-landing-message h2 — alternative heading style
- .mzc-landing-message h3 — alternative heading style
- .mzc-landing-message p — paragraph text below the heading

### Form

- .mzc-landing-form-wrapper — wrapper for the entire form area
- .mzc-form-group — the input and button row container
- .mzc-form-input — the email input field
- .mzc-form-button — the subscribe button

### Feedback and GDPR

- .mzc-form-feedback — the success or error message area below the form
- .mzc-gdpr-consent — wrapper for the GDPR consent checkbox and label

### Example

```css
.mzc-landing-container {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.mzc-landing-content {
  background: rgba(255, 255, 255, 0.95);
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.mzc-form-button {
  background: #764ba2;
  color: #ffffff;
}

.mzc-form-button:hover {
  background: #667eea;
}
```

---

## 7. GDPR Compliance

The module integrates with PrestaShop's official GDPR module (psgdpr) through three hooks.

### Consent Checkbox

When psgdpr is installed and configured, a consent checkbox with your configured consent message appears below the email field on the landing page. The subscribe button is disabled until the visitor checks the box. This is handled automatically by the psgdpr module's JavaScript.

To configure the consent message, go to Modules > Module Manager > Official GDPR Compliance > Configure, and set up the consent message for MZC Landing Newsletter.

### Data Deletion

When a GDPR data deletion request is processed, the module deletes any matching email address from the newsletter subscription table and cleans up any related rate-limiting records.

### Data Export

When a GDPR data export request is processed, the module returns all newsletter subscription records matching the requested email address, including subscription date and registration IP.

---

## 8. Google Tag Manager and Analytics

The landing page renders three standard PrestaShop hooks that tracking modules use:

- displayHeader — rendered inside the HTML head section. Used by Google Tag Manager, Google Analytics, Facebook Pixel, and similar modules to inject their tracking scripts and dataLayer initialization.
- displayAfterBodyOpeningTag — rendered immediately after the body opening tag. Used by Google Tag Manager for its noscript iframe fallback.
- displayBeforeBodyClosingTag — rendered before the body closing tag. Used by some tracking modules for deferred script loading.

This means any tracking module that follows PrestaShop's standard hook system will work on the landing page without any additional configuration. Verified compatible with:

- Google Tag Manager (gtmmodule)
- PrestaShop Google Analytics (ps_googleanalytics)
- PrestaShop Marketing with Google (psxmarketingwithgoogle)

---

## 9. Multi-Store Setup

The module fully supports PrestaShop's multi-store feature.

### Per-Store Configuration

When multi-store is active and you select a specific shop in the shop context selector:

- Each configuration field shows an override checkbox
- Check the box to set a store-specific value that overrides the global default
- Uncheck the box to inherit the value from the All Shops configuration

### Common Scenarios

- Enable landing mode for a new store while keeping other stores live: Set MZC_LANDING_ENABLED to No at the All Shops level, then override it to Yes for the specific store
- Use different messages per store: Set a default message at All Shops, then override with store-specific messages where needed
- Use different CSS per store: Each store can have its own visual style by overriding the Custom CSS field

---

## 10. Subscriber Management

### Subscriber List

The Subscribers panel on the configuration page shows all email addresses collected through the landing page, identified by the source tag mzc_landing_page. The list displays:

- Email address
- Language at time of subscription
- Registration IP address
- Subscription date

The list is paginated at 20 entries per page. Use the page navigation links at the bottom to browse.

Click Refresh list to reload the subscriber data.

### CSV Export

Click Export CSV to download all landing page subscribers as a comma-separated values file. The export includes all subscribers (not just the current page), with columns: email, language, IP, and date.

### Integration with ps_emailsubscription

Since the module uses PrestaShop's native newsletter table, subscribers collected on the landing page also appear in:

- The ps_emailsubscription module's subscriber list
- Any newsletter export tool that reads from the emailsubscription table
- Mailchimp, Sendinblue, and other newsletter integrations connected to PrestaShop

---

## 11. SEO Configuration

The landing page automatically loads SEO metadata from your store's configuration for the index (home) page:

- Meta title — used as the HTML page title
- Meta description — rendered as a meta description tag
- Meta keywords — rendered as a meta keywords tag (if configured)

To configure these values, go to Shop Parameters > Traffic & SEO > SEO & URLs, find the page labeled index, and edit the meta title, meta description, and meta keywords.

If no meta title is configured, the module falls back to the store name.

The landing page sends an HTTP 503 (Service Unavailable) status code with a Retry-After header. This tells search engines that the site is temporarily unavailable and they should return later, preserving your existing search rankings.

---

## 12. Interaction with Maintenance Mode

Important: Disable PrestaShop's built-in maintenance mode when using Landing Page Mode.

PrestaShop's maintenance mode (Shop Parameters > General > Maintenance) and this module's Landing Page Mode are independent features. If both are enabled simultaneously, PrestaShop's maintenance mode takes priority because it executes earlier in the request lifecycle, before this module's hook runs.

To access the maintenance settings, go to Shop Parameters > General > Maintenance in your back office and set Enable Store to Yes.

Recommended workflow:

1. Disable PrestaShop maintenance mode (set Enable Store to Yes)
2. Enable MZC Landing Newsletter's Landing Page Mode
3. Work on your store — you can access it via your whitelisted IP
4. When ready to launch, disable Landing Page Mode
5. Your store is immediately live for all visitors

---

## 13. Admin Bypass

When Landing Page Mode is enabled, the following users can still access the full store:

### IP Whitelist

Any IP address listed in Shop Parameters > General > Maintenance > Maintenance IP bypasses the landing page. Add your IP address there to work on your store while visitors see the landing page. Multiple IPs can be separated by commas. CIDR notation is supported (e.g., 192.168.1.0/24).

### Logged-in Administrators

If the PS_MAINTENANCE_ALLOW_ADMINS setting is enabled, any user with an active back-office admin session automatically bypasses the landing page. The module reads the PrestaShop admin cookie to detect logged-in administrators.

---

## 14. Rate Limiting

To prevent spam and abuse, the subscribe endpoint enforces a rate limit of 3 submissions per IP address per 10-minute window.

When the limit is exceeded, the visitor sees a message asking them to try again later. The rate limit counter resets automatically after 10 minutes.

Rate-limiting data (IP addresses and timestamps) is stored in a dedicated database table and automatically cleaned up. Only the current IP's expired entries are purged on each request, with a 1% probabilistic global cleanup to prevent table growth.

---

## 15. Security Features

### CSRF Protection

The subscribe form includes a time-rotating CSRF token that changes every hour. Both the current and previous hour's tokens are accepted during validation to prevent edge-case rejections at the hour boundary.

### XSS Protection

Custom CSS entered in the back office is sanitized before saving. HTML tags are stripped and style tag breakout sequences are neutralized to prevent script injection.

### Content Security Policy

The landing page sends a Content-Security-Policy header that restricts script sources to self and inline (required for tracking modules), and allows styles from self, inline, and HTTPS sources (required for web fonts).

### Email Validation

Email addresses are validated using PrestaShop's built-in Validate::isEmail() method before any database operation.

---

## 16. Translations

The module ships with complete translations for 5 languages:

- English (en)
- Polish (pl)
- French (fr)
- Spanish (es)
- Italian (it)

Each translation file covers all 87 translatable strings across the module class, subscribe controller, and landing page template.

### Adding or Editing Translations

To translate the module into additional languages or modify existing translations:

1. Go to International > Translations in your back office
2. Select Installed modules translations from the Type dropdown
3. Select the target language
4. Find MZC Landing Newsletter in the module list
5. Enter your translations and click Save

PrestaShop saves the translation file to modules/mzclandingnewsletter/translations/ automatically.

---

## 17. Troubleshooting

### Landing page not showing

- Verify MZC_LANDING_ENABLED is set to Yes in the module configuration
- Check that PrestaShop maintenance mode is disabled (Shop Parameters > General > Maintenance > Enable Store = Yes)
- Verify your IP is not in the maintenance IP whitelist
- Clear PrestaShop cache (Advanced Parameters > Performance > Clear Cache)

### GDPR checkbox not appearing

- Verify the psgdpr module is installed and enabled
- Go to psgdpr configuration and ensure a consent message is configured for MZC Landing Newsletter
- Clear PrestaShop cache and reload the landing page

### Fonts not loading correctly

- This usually occurs when theme fonts (e.g., Google Fonts) are loaded via the displayHeader hook. The module renders this hook, so fonts should load. If they do not, clear your browser cache with Ctrl+Shift+R (Cmd+Shift+R on Mac)
- Check browser developer tools console for Content Security Policy errors

### Tracking scripts not firing

- Verify your tracking module uses standard PrestaShop hooks (displayHeader, displayAfterBodyOpeningTag, or displayBeforeBodyClosingTag)
- Check browser developer tools console for JavaScript errors
- Some tracking modules may require specific page context that is not available on the landing page

### Subscribe button not working

- Check browser developer tools console for JavaScript errors
- Verify that the psgdpr consent checkbox is checked (if GDPR is enabled)
- Check if rate limiting has been triggered (max 3 per IP per 10 minutes)

### Subscribers not appearing in list

- Click Refresh list on the configuration page
- Verify you are viewing the correct shop context in multi-store setups
- Check the database table ps_emailsubscription for entries with http_referer = mzc_landing_page

---

## 18. Uninstallation

1. Go to Modules > Module Manager
2. Find MZC Landing Newsletter
3. Click the dropdown arrow and select Uninstall
4. Confirm the uninstallation

The module will:

- Remove all configuration values (MZC_LANDING_ENABLED, MZC_LANDING_MESSAGE, MZC_LANDING_CSS)
- Drop the rate-limiting table (mzc_landing_ratelimit)
- Unregister all hooks

Newsletter subscribers in the emailsubscription table are NOT deleted during uninstallation, as they are shared with the ps_emailsubscription module.

---

## 19. Support

For support, bug reports, or feature requests, contact us through the PrestaShop Addons messaging system on the module's product page.

When reporting an issue, please include:

- PrestaShop version
- PHP version
- Theme name and version
- List of other installed modules
- Browser developer tools console output (if applicable)
- Steps to reproduce the issue
