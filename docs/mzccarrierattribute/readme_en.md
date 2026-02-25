# MZC Carrier Attribute — Documentation

## Version 1.0.0

## Author: Marcin Zbijowski Consulting

## Compatibility: PrestaShop 8.0.0 – 9.x | PHP 8.1+

---

## Table of Contents

1. Overview
2. Requirements
3. Installation
4. How It Works
5. Configuration
6. Examples
7. Cart Behavior
8. Carrier Updates
9. Multi-Store Setup
10. Performance
11. Translations
12. Troubleshooting
13. Uninstallation
14. Support

---

## 1. Overview

MZC Carrier Attribute lets you restrict which shipping carriers are available at checkout based on product attribute values (e.g. sizes, materials, weights).

It uses a whitelist model: for each attribute value, you select which carriers are allowed. Attribute values without any carriers selected impose no restriction — all carriers remain available. Only attribute values with explicit carrier assignments limit the shipping options.

When a customer adds products with restricted attribute values to the cart, the module automatically removes disallowed carriers from the checkout delivery options. If multiple restricted attribute values are in the cart, only carriers allowed by ALL of them will be available.

Simple products without combinations are never affected by this module.

---

## 2. Requirements

- PrestaShop 8.0.0 or later (compatible up to 9.x)
- PHP 8.1 or later
- At least one active carrier configured
- At least one attribute group with attribute values (e.g. Size: S, M, L, XL)

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
2. Upload the mzccarrierattribute folder to your PrestaShop's modules directory via FTP
3. Go to Modules > Module Manager in the back office
4. Search for MZC Carrier Attribute
5. Click Install, then Configure

After installation, a link to the module also appears in the back-office left menu under **Shipping** > **Carrier Attributes**, providing quick access to the configuration page.

---

## 4. How It Works

### The Whitelist Model

The module operates on a simple whitelist principle:

1. Each product attribute value (e.g. "XXL" in the "Size" group) can be assigned one or more allowed carriers
2. If an attribute value has no carriers assigned, it imposes no restriction — all carriers remain available
3. If an attribute value has carriers assigned, only those carriers are allowed when a product with that attribute is in the cart
4. When multiple restricted attribute values are in the cart, the module computes the intersection — only carriers that appear in ALL allowlists are available

### Example Logic

Consider these rules:

- Size "XXL" → allowed carriers: Carrier A, Carrier B
- Size "S" → no carriers assigned (no restriction)
- Material "Glass" → allowed carriers: Carrier B, Carrier C

Scenarios:

- Cart contains a product in size S → all carriers available (no restriction)
- Cart contains a product in size XXL → only Carrier A and B available
- Cart contains a product in size XXL and material Glass → only Carrier B available (intersection of {A, B} and {B, C})
- Cart contains a product in size XXL and another in size S → only Carrier A and B available (only XXL imposes restrictions)

### Hooks Used

The module uses three PrestaShop hooks:

- **actionFilterDeliveryOptionList** — the core filtering hook. Removes disallowed carriers from the delivery options presented at checkout.
- **actionCarrierUpdate** — invalidates the internal cache when a carrier is modified in the back office, ensuring rules always reference current carrier data.
- **actionPresentCart** — annotates the shipping subtotal label in the cart summary when restrictions are active, so the customer sees a notice that some shipping methods are restricted by product attributes.

---

## 5. Configuration

Navigate to Modules > Module Manager, find MZC Carrier Attribute, and click Configure. Alternatively, use the **Shipping** > **Carrier Attributes** shortcut in the back-office left menu.

### Rules Panel

The configuration page shows all attribute groups and their values in collapsible panels. For each attribute value, a row of checkboxes lists all active carriers. Check the carriers you want to allow for that attribute value.

### Filtering

Use the Show only configured attributes button to filter the view to only those attribute values that have at least one carrier assigned. This is helpful when you have many attributes but only a few rules.

Click Show all attributes to return to the full view.

### Saving

Click Save Rules at the bottom of the page to save all rules at once. The module saves all rules in a single transaction for consistency.

A success message confirms the save. If no attributes are found, an error message is displayed.

---

## 6. Examples

### Use Case 1: Oversized Items

You sell furniture and some items are only shipped by freight carriers.

1. Create an attribute group called "Shipping Class" with values like "Standard" and "Freight Only"
2. Assign "Freight Only" → your freight carriers
3. Leave "Standard" with no carriers selected (all carriers allowed)
4. Apply "Freight Only" to oversized product combinations

Result: Oversized products only show freight carriers at checkout.

### Use Case 2: Regional Restrictions

You sell products that can only be shipped by certain regional carriers due to material restrictions.

1. Use an existing attribute like "Material" with values such as "Lithium Battery", "Flammable", "Standard"
2. Assign "Lithium Battery" → carriers certified for hazardous materials
3. Assign "Flammable" → carriers certified for flammable goods
4. Leave "Standard" with no carriers selected

Result: Products with restricted materials only show certified carriers.

### Use Case 3: Size-Based Shipping

You sell clothing and XXL sizes require a specific carrier with larger packaging.

1. In the "Size" attribute group, assign "XXL" and "XXXL" → your large-package carrier
2. Leave all other sizes with no carriers selected

Result: Orders containing XXL or XXXL items only show the large-package carrier.

---

## 7. Cart Behavior

### Multiple Products

When the cart contains multiple products with restricted attributes, the module computes the intersection of all allowed carrier sets. This means:

- If Product A allows carriers {1, 2, 3} and Product B allows carriers {2, 3, 4}, only carriers {2, 3} are available
- If the intersection is empty (no carrier is allowed by all restricted products), all delivery options are removed and the customer cannot proceed to checkout

### Shipping Subtotal Notice

When carrier restrictions are active for the current cart, the module appends the text "(some methods restricted by product attributes)" to the shipping subtotal label in the cart summary. This provides transparency to the customer about why some shipping methods may not be available.

### Mixed Cart

If the cart contains both restricted and unrestricted products, only the restricted products' rules apply. Unrestricted products (simple products or those with unconfigured attributes) do not limit carrier availability.

---

## 8. Carrier Updates

When you modify a carrier in the PrestaShop back office (rename, change zones, update pricing), the module automatically invalidates its internal cache on the next page load. This ensures that rules always reference the current carrier data.

PrestaShop creates a new carrier record with a new ID when you edit a carrier, but preserves the carrier's reference ID. The module uses reference IDs, so rules remain valid even after carrier edits.

If you delete a carrier entirely, its rules become inactive but are not automatically removed from the configuration. The module gracefully handles references to deleted carriers by skipping them during filtering.

---

## 9. Multi-Store Setup

The module supports PrestaShop's multi-store feature. Each store can have its own carrier-attribute rules configured independently.

The rules are stored per-shop in the Configuration table, so switching between shop contexts in the back office shows and saves rules for the selected shop.

---

## 10. Performance

### Caching Strategy

The module uses a two-level caching strategy:

1. **Configuration cache** — rules are serialized as JSON in the ps_configuration table for fast reads. This avoids querying the rules table on every page load.
2. **Per-request memory cache** — once loaded, rules and carrier reference mappings are cached in static properties for the duration of the request.

The cache is invalidated automatically when:

- Rules are saved via the configuration page
- A carrier is updated in the back office

### Database

The module creates a single table (mzc_carrier_attr_rule) with indexed columns for efficient lookups. The table uses a composite unique key to prevent duplicate rules.

---

## 11. Translations

The module ships with complete translations for 5 languages:

- English (en)
- Polish (pl)
- French (fr)
- Spanish (es)
- Italian (it)

Each translation file covers all 20 translatable strings across the module class, rules form template, and warning templates.

### Adding or Editing Translations

To translate the module into additional languages or modify existing translations:

1. Go to International > Translations in your back office
2. Select Installed modules translations from the Type dropdown
3. Select the target language
4. Find MZC Carrier Attribute in the module list
5. Enter your translations and click Save

PrestaShop saves the translation file to modules/mzccarrierattribute/translations/ automatically.

---

## 12. Troubleshooting

### Carrier restrictions not applying

- Verify that rules are saved: go to the module configuration and check that the correct carriers are checked for the relevant attribute values
- Make sure the carrier is active and not deleted
- Clear PrestaShop cache (Advanced Parameters > Performance > Clear Cache)
- Verify the product has a combination with the restricted attribute value (simple products are not affected)

### All carriers disappearing at checkout

- This happens when the intersection of all restricted attribute values results in an empty set (no carrier is allowed by all restricted products in the cart)
- Review your rules to ensure that at least one carrier is common across all restricted attribute values that might be in the cart simultaneously
- Use the "Show only configured attributes" filter to review all active rules

### Rules not showing on configuration page

- Ensure you have at least one attribute group with attribute values
- Ensure you have at least one active carrier
- If either is missing, the module shows a warning instead of the rules form

### Configuration page slow

- If you have hundreds of attribute values, the configuration page may load slowly due to the large form
- Use the "Show only configured attributes" filter to reduce the displayed data
- Consider creating a dedicated "Shipping Class" attribute group with fewer values instead of configuring rules for every attribute

---

## 13. Uninstallation

1. Go to Modules > Module Manager
2. Find MZC Carrier Attribute
3. Click the dropdown arrow and select Uninstall
4. Confirm the uninstallation

The module will:

- Drop the rules table (mzc_carrier_attr_rule)
- Remove the configuration value (MZC_CARRIER_ATTR_RULES)
- Unregister all hooks

All carrier-attribute rules will be permanently deleted.

---

## 14. Support

For support, bug reports, or feature requests, contact us through the PrestaShop Addons messaging system on the module's product page.

When reporting an issue, please include:

- PrestaShop version
- PHP version
- Theme name and version
- List of active carriers and attribute groups
- Steps to reproduce the issue
