# MZC Carrier Attribute — Documentation

## Version 1.1.0

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

It uses a whitelist model with compound rules: you create rules that combine one or more attribute-value conditions with a set of allowed carriers. A rule applies when ALL its conditions match a product combination. For example, you can create a rule like "Size 40×50 + Frame Framed → Courier only", while leaving "Size 40×50 + Frame Unframed" without a rule so that all carriers remain available. Single-condition rules work exactly like simple attribute → carrier mappings.

When a customer adds products with restricted attribute combinations to the cart, the module automatically removes disallowed carriers from the checkout delivery options. If multiple rules match products in the cart, only carriers allowed by ALL matching rules will be available.

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

### The Whitelist Model — Compound Rules

The module operates on a whitelist principle with compound multi-condition rules:

1. Each rule consists of one or more attribute-value conditions plus a set of allowed carriers
2. A rule applies when ALL its conditions match a product combination's attributes
3. Single-condition rules (one attribute-value → carriers) work exactly like the previous model — backward compatible
4. Compound rules (2+ conditions) allow precise control over specific attribute combinations (e.g. "Size 40×50 + Frame Framed → Courier only")
5. If no rule matches a product combination, no restriction is imposed — all carriers remain available
6. When multiple rules match products in the cart, the module computes the intersection — only carriers that appear in ALL matching rules' allowlists are available

### Example Logic

Consider these compound rules:

- Rule 1: Size "40×50" + Frame "Framed" → allowed carriers: Courier only
- Rule 2: Size "50×70" + Frame "Framed" → allowed carriers: Courier only
- No rule for unframed combinations → all carriers available

Scenarios:

- Cart contains a poster 40×50 Unframed → no rule matches → all carriers available
- Cart contains a poster 40×50 Framed → Rule 1 matches → only Courier available
- Cart contains a poster 50×70 Framed → Rule 2 matches → only Courier available
- Cart contains a poster 40×50 Framed and a poster 50×70 Framed → Rules 1 and 2 match → intersection of {Courier} and {Courier} = Courier only
- Cart contains a poster 40×50 Framed and a poster 40×50 Unframed → Rule 1 matches for the framed one, no rule for unframed → only Courier available

### Hooks Used

The module uses three PrestaShop hooks:

- **actionFilterDeliveryOptionList** — the core filtering hook. Removes disallowed carriers from the delivery options presented at checkout.
- **actionCarrierUpdate** — invalidates the internal cache when a carrier is modified in the back office, ensuring rules always reference current carrier data.
- **actionPresentCart** — annotates the shipping subtotal label in the cart summary when restrictions are active, so the customer sees a notice that some shipping methods are restricted by product attributes.

---

## 5. Configuration

Navigate to Modules > Module Manager, find MZC Carrier Attribute, and click Configure. Alternatively, use the **Shipping** > **Carrier Attributes** shortcut in the back-office left menu.

### Rule Builder

The configuration page features a dynamic rule builder. Rules are displayed as cards, each containing its conditions and allowed carriers.

- Click **Add Rule** to create a new rule
- Each rule has one or more condition selectors (attribute group → value dropdowns) and a set of carrier checkboxes
- Click **Add condition** within a rule to add another attribute-value condition to that rule (creating a compound rule)
- Remove individual conditions with the delete button next to each condition
- Remove an entire rule with the delete button on the rule card

### Saving

Click **Save Rules** at the bottom of the page to save all rules at once. The module saves all rules in a single database transaction for consistency.

A success message confirms the save. If no attributes or carriers are found, a warning message is displayed.

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

### Use Case 4: Framed vs Unframed Posters (Compound Rules)

You sell posters in various sizes, both framed and unframed. Large framed posters are too bulky for parcel lockers and must ship via courier.

1. Create compound rules combining Size and Frame conditions:
   - Size "40×50" + Frame "Framed" → Courier only
   - Size "50×70" + Frame "Framed" → Courier only
   - Size "70×100" + Frame "Framed" → Courier only
2. Leave all unframed combinations without rules (all carriers allowed)
3. Leave small framed sizes (e.g. 20×30) without rules if they fit in parcel lockers

Result: Large framed posters only show courier delivery, while unframed posters of any size and small framed posters show all carriers including parcel lockers.

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

The module creates 3 tables to store compound rules:

- **mzc_carrier_attr_rule_group** — rule groups per shop. Each group represents one compound rule.
- **mzc_carrier_attr_rule_condition** — conditions per group. Each condition specifies an attribute group and attribute value.
- **mzc_carrier_attr_rule_carrier** — allowed carriers per group. Each entry links a rule group to a permitted carrier.

All tables use indexed columns for efficient lookups and foreign keys for referential integrity.

---

## 11. Translations

The module ships with complete translations for 5 languages:

- English (en)
- Polish (pl)
- French (fr)
- Spanish (es)
- Italian (it)

Each translation file covers all 22 translatable strings across the module class, rules form template, and warning templates.

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
- Open the rule builder to review all active rules and their carrier selections

### Rules not showing on configuration page

- Ensure you have at least one attribute group with attribute values
- Ensure you have at least one active carrier
- If either is missing, the module shows a warning instead of the rules form

### Configuration page slow

- If you have hundreds of attribute values, the configuration page may load slowly due to the large form
- Consider creating a dedicated "Shipping Class" attribute group with fewer values instead of configuring rules for every attribute

---

## 13. Uninstallation

1. Go to Modules > Module Manager
2. Find MZC Carrier Attribute
3. Click the dropdown arrow and select Uninstall
4. Confirm the uninstallation

The module will:

- Drop all rule tables (mzc_carrier_attr_rule_group, mzc_carrier_attr_rule_condition, mzc_carrier_attr_rule_carrier)
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
