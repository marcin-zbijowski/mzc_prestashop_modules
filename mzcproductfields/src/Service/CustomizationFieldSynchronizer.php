<?php

declare(strict_types=1);

namespace MZConsulting\MzcProductFields\Service;

use CustomizationField;
use Db;
use Language;
use MzcProductFieldsField;
use Product;

/**
 * Lazily creates/reuses the native `customization_field` row backing one of
 * our fields for a given product, and keeps `mzc_productfields_field_customization`
 * (our own bookkeeping — `customization_field` has no FK back to us) in sync.
 *
 * `is_module` is set to 1 so PrestaShop's own front-office rendering
 * (Product::getCustomizationFields(), filtered `AND !cf.is_module` for front
 * controllers — confirmed in core source) skips these rows entirely; we
 * render them ourselves (see the front-office hook, plan section 4).
 *
 * The native `required` flag is deliberately left false regardless of our own
 * field's required-ness: core's OWN required-field validation
 * (Product::hasAllRequiredCustomizableFields(), run again at the real
 * add-to-cart step) checks for the NATIVE 'textField' . id_customization_field
 * input name (confirmed in ProductController::textRecord()) — which we never
 * populate, since our own markup submits `mzc_field_{id}` and writes straight
 * to customized_data via CartFieldValueWriter (see savecustomization.php's
 * docblock). Confirmed as a real, reproducible bug: setting this flag to true
 * made core reject the add-to-cart for ANY required field, even one the
 * customer had correctly filled in and that our OWN CartFieldValueWriter::write()
 * had already validated — because core was checking an input name that
 * genuinely never gets submitted. Our own validation already runs earlier
 * (at the confirmation step, before the customer can even reach add-to-cart)
 * and is the sole source of truth for required-ness.
 *
 * All lookups/creates are per current shop context, matching
 * `customization_field`'s own `multilang_shop` definition.
 */
final class CustomizationFieldSynchronizer
{
    /**
     * @return int the id_customization_field to use as the native "index" for this (field, product) pair
     */
    public function getOrCreate(MzcProductFieldsField $field, int $idProduct): int
    {
        $this->ensureProductMarkedCustomizable($idProduct);

        $existingId = $this->getMappedId((int) $field->id, $idProduct);
        if ($existingId !== null) {
            return $existingId;
        }

        $customizationField = new CustomizationField();
        $customizationField->id_product = $idProduct;
        $customizationField->type = Product::CUSTOMIZE_TEXTFIELD;
        $customizationField->required = false;
        $customizationField->is_module = true;
        $customizationField->name = $this->buildNamePerLang($field);
        $customizationField->add();

        $idCustomizationField = (int) $customizationField->id;

        Db::getInstance()->insert('mzc_productfields_field_customization', [
            'id_mzc_productfields_field' => (int) $field->id,
            'id_product' => $idProduct,
            'id_customization_field' => $idCustomizationField,
        ]);

        return $idCustomizationField;
    }

    /**
     * Creating a `customization_field` row through the native admin product
     * form always flips the product's own `customizable` flag to 1 alongside
     * it — we create these rows programmatically instead, bypassing that, so
     * we have to set the flag ourselves. Confirmed a real, shipped bug via
     * direct production reproduction: with `customizable` left at 0, core's
     * `Cart::updateQty()` skips its own `_updateCustomizationQuantity()` call
     * entirely (it's gated on `if ($product->customizable)`), so the
     * `customization` row our own `_addCustomization()` creates — with
     * quantity intentionally 0 as a placeholder, expecting that later core
     * call to fill in the real quantity — never gets updated and stays at
     * quantity 0 forever. That in turn breaks `Cart::getProducts()`'s own
     * customization JOIN and the front-office's presented `id_customization`
     * for the product, which cascades into `Cart::deleteProduct()`'s
     * `cart_product` DELETE never matching the real row — the underlying
     * cause of "can't remove a personalized product from the cart" on
     * PS 8.2.7 production. A cheap, idempotent UPDATE (only writes when
     * still 0) run on every call rather than only on first-creation, so
     * products created before this fix existed get repaired the next time
     * their fields are touched, not just brand-new ones.
     *
     * Both `product` AND `product_shop` carry their own `customizable`
     * column (confirmed on this multistore-capable install) — the `Product`
     * object resolves the property a running request actually reads from
     * `product_shop`, not `product`, so setting only the former silently
     * has no effect at all. Both must be kept in sync.
     */
    private function ensureProductMarkedCustomizable(int $idProduct): void
    {
        Db::getInstance()->update(
            'product',
            ['customizable' => 1],
            '`id_product` = ' . $idProduct . ' AND `customizable` = 0'
        );

        Db::getInstance()->update(
            'product_shop',
            ['customizable' => 1],
            '`id_product` = ' . $idProduct . ' AND `customizable` = 0'
        );
    }

    /**
     * Soft-deletes every native customization_field row this module ever
     * created (across all products), and clears our mapping table — used by
     * the module's uninstall() so no orphaned is_module rows are left behind.
     * Historical customized_data / customization rows are untouched (they
     * belong to already-placed carts/orders, exactly like core's own
     * behaviour when a customizable product is deleted).
     */
    public function removeAll(): void
    {
        // Guards module upgrades from a version installed before this mapping
        // table existed: uninstall() runs against the OLD schema during a
        // reset, so the table may genuinely not be there yet.
        if (!Db::getInstance()->executeS('SHOW TABLES LIKE "' . _DB_PREFIX_ . 'mzc_productfields_field_customization"')) {
            return;
        }

        $ids = Db::getInstance()->executeS(
            'SELECT `id_customization_field` FROM `' . _DB_PREFIX_ . 'mzc_productfields_field_customization`'
        );

        foreach ($ids ?: [] as $row) {
            $customizationField = new CustomizationField((int) $row['id_customization_field']);
            if ($customizationField->id) {
                $customizationField->delete();
            }
        }

        Db::getInstance()->execute('TRUNCATE TABLE `' . _DB_PREFIX_ . 'mzc_productfields_field_customization`');
    }

    private function getMappedId(int $idField, int $idProduct): ?int
    {
        $id = Db::getInstance()->getValue(
            'SELECT `id_customization_field` FROM `' . _DB_PREFIX_ . 'mzc_productfields_field_customization`
             WHERE `id_mzc_productfields_field` = ' . $idField . ' AND `id_product` = ' . $idProduct
        );

        if ($id === false) {
            return null;
        }

        // The mapping can outlive the native row if something else deleted it
        // out-of-band; treat that as "not mapped" so a fresh one gets created.
        $customizationField = new CustomizationField((int) $id);
        if (!$customizationField->id || (bool) $customizationField->is_deleted) {
            return null;
        }

        return (int) $id;
    }

    /**
     * @return array<int,string> id_lang => label, so the native field carries
     *                            a sensible name wherever core itself surfaces
     *                            it (e.g. the admin order detail's raw
     *                            customization list), even though we render
     *                            our own front-office markup.
     */
    private function buildNamePerLang(MzcProductFieldsField $field): array
    {
        $names = [];
        foreach (Language::getLanguages(false) as $lang) {
            $idLang = (int) $lang['id_lang'];
            $names[$idLang] = is_array($field->label) ? ($field->label[$idLang] ?? '') : (string) $field->label;
        }

        return $names;
    }
}
