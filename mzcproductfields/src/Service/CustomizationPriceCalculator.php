<?php

declare(strict_types=1);

namespace MZConsulting\MzcProductFields\Service;

use Address;
use Configuration;
use Context;
use MzcProductFieldsField;
use Product;
use TaxManagerFactory;

/**
 * Converts a field's (or a select/radio/checkbox/multiselect option's)
 * configured price into the net amount that must be written into
 * `customized_data.price`.
 *
 * The native Customization mechanism (Customization::getCustomizationPrice(),
 * summed in Product::getPriceStatic() BEFORE tax) always taxes the combined
 * base-price-plus-surcharge at the PRODUCT's own tax rate — there is no way
 * to give a single `customized_data.price` row its own tax rate directly.
 * Anvanto's `custom_tax` (a field-level tax rate override, independent of the
 * product's own) is real, live data on 14/22 fields, so this has to be
 * resolved here rather than assumed away: we compute the field's true gross
 * amount at ITS OWN rate, then back-convert it into whatever net figure
 * yields that exact gross once the PRODUCT's rate is re-applied on top. That
 * keeps the customer-facing gross total exactly correct regardless of a
 * tax-rate mismatch between field and product; see
 * plan-modul-live-preview-personalizacji.md section 8 and the Task #5 spike.
 *
 * All amounts are decimal strings in and out, at full 6-digit precision —
 * per the owner's explicit requirement to avoid Anvanto's decimal(12,2)
 * rounding artifacts (see MzcProductFieldsField's docblock).
 */
final class CustomizationPriceCalculator
{
    /**
     * @param string $priceAmount the field's (or option's) configured amount, as entered in the admin
     * @param int $priceType MzcProductFieldsField::PRICE_TYPE_NET|PRICE_TYPE_GROSS
     * @param int|string|null $taxRulesGroupId this field's (or option's) own tax rules group override; null/0 = use the product's own.
     *                                         Loosely typed on purpose: ObjectModel-hydrated properties come back as
     *                                         strings on PS 8.2.7 (unlike PS9, which casts per the field definition) —
     *                                         confirmed by a real TypeError here during cross-version testing.
     * @param int $idProduct the product the surcharge is being added to (its own tax rules group is the fallback, and is always what the native mechanism re-taxes the total with)
     *
     * @return string decimal(20,6) string — the net amount to write into customized_data.price
     */
    public function calculateNetSurcharge(
        string $priceAmount,
        int $priceType,
        $taxRulesGroupId,
        int $idProduct
    ): string {
        if ((float) $priceAmount === 0.0) {
            return '0.000000';
        }

        $address = $this->getTaxAddress();
        $productTaxRulesGroupId = (int) (new Product($idProduct))->id_tax_rules_group;
        $fieldGrossAmount = $this->calculateDisplayGross($priceAmount, $priceType, $taxRulesGroupId, $idProduct);

        // Re-express that gross amount as a net figure taxed at the PRODUCT's
        // own rate — this is the only way the native mechanism can reproduce
        // the correct gross contribution when field and product tax rates
        // differ (see class docblock). When they're the same rate, this is
        // just the field's own net amount, unchanged.
        $netSurcharge = $this->getTaxCalculator($productTaxRulesGroupId, $address)->removeTaxes((float) $fieldGrossAmount);

        return number_format($netSurcharge, 6, '.', '');
    }

    /**
     * The field's (or option's) own true gross amount, at ITS OWN tax rate —
     * i.e. exactly the real-money impact this field has on the order total,
     * unaffected by the product-rate re-expression calculateNetSurcharge()
     * needs internally for storage. This is what the front-office should
     * display next to a paid field ("+12,30 zł"), not the stored net figure.
     *
     * @param int|string|null $taxRulesGroupId see calculateNetSurcharge()
     */
    public function calculateDisplayGross(
        string $priceAmount,
        int $priceType,
        $taxRulesGroupId,
        int $idProduct
    ): string {
        if ((float) $priceAmount === 0.0) {
            return '0.000000';
        }

        if ($priceType === MzcProductFieldsField::PRICE_TYPE_GROSS) {
            return number_format((float) $priceAmount, 6, '.', '');
        }

        $productTaxRulesGroupId = (int) (new Product($idProduct))->id_tax_rules_group;
        $fieldTaxRulesGroupId = $taxRulesGroupId !== null && (int) $taxRulesGroupId !== 0
            ? (int) $taxRulesGroupId
            : $productTaxRulesGroupId;

        $gross = $this->getTaxCalculator($fieldTaxRulesGroupId, $this->getTaxAddress())->addTaxes((float) $priceAmount);

        return number_format($gross, 6, '.', '');
    }

    /**
     * Converts a pre-summed net total (e.g. CartFieldValueWriter's
     * total_net_surcharge, already expressed relative to the product's own
     * rate — see calculateNetSurcharge()'s docblock) into the gross figure
     * the customer will actually see added to their cart total. Re-taxing at
     * the product's own rate is exactly what the native mechanism does when
     * it sums customized_data.price into Product::getPriceStatic(), so this
     * reproduces that total precisely — used by the content-confirmation
     * step to show "this will add X to your total" before add-to-cart.
     */
    public function netToGrossAtProductRate(string $netAmount, int $idProduct): string
    {
        if ((float) $netAmount === 0.0) {
            return '0.000000';
        }

        $productTaxRulesGroupId = (int) (new Product($idProduct))->id_tax_rules_group;
        $gross = $this->getTaxCalculator($productTaxRulesGroupId, $this->getTaxAddress())->addTaxes((float) $netAmount);

        return number_format($gross, 6, '.', '');
    }

    private function getTaxCalculator(int $idTaxRulesGroup, Address $address)
    {
        return TaxManagerFactory::getManager($address, $idTaxRulesGroup)->getTaxCalculator();
    }

    /**
     * Mirrors anProductFieldsTax::addTax()'s address resolution exactly, so
     * our tax rate lookups agree with the rest of the cart/order pipeline.
     */
    private function getTaxAddress(): Address
    {
        $cart = Context::getContext()->cart;
        $addressType = Configuration::get('PS_TAX_ADDRESS_TYPE');

        if ($cart !== null && !empty($cart->{$addressType})) {
            return Address::initialize((int) $cart->{$addressType}, true);
        }

        return Address::initialize(0, true);
    }
}
