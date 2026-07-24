<?php

declare(strict_types=1);

namespace MZConsulting\MzcProductFields\Service;

use Cart;
use Db;
use MzcProductFieldsField;
use MzcProductFieldsFieldValue;
use Product;

/**
 * Writes a product's submitted personalization values into the cart via the
 * native Customization mechanism (Cart::addTextFieldToProduct() /
 * deleteCustomizationToProduct()), then patches the resulting
 * `customized_data.price` row — which the native mechanism always inserts as
 * 0 — with the actual computed net surcharge (see CustomizationPriceCalculator's
 * docblock for why that has to be a net, product-tax-rate-relative figure).
 *
 * Every value we write goes through Product::CUSTOMIZE_TEXTFIELD, including
 * select/radio/checkbox/multiselect: we store the human-readable selected
 * label(s) as the text value (shown as-is in cart/order/email, exactly where
 * a free-text answer would appear) and compute price separately from the
 * selected option row(s) — there's no need for a dedicated native "choice"
 * customization type since none exists in core.
 */
final class CartFieldValueWriter
{
    private CustomizationFieldSynchronizer $synchronizer;
    private CustomizationPriceCalculator $priceCalculator;

    public function __construct(
        CustomizationFieldSynchronizer $synchronizer,
        CustomizationPriceCalculator $priceCalculator
    ) {
        $this->synchronizer = $synchronizer;
        $this->priceCalculator = $priceCalculator;
    }

    /**
     * @param array<int,mixed> $submittedValues our field id => raw submitted value
     *                                           (string for text/textarea/date;
     *                                           string|array<string> of
     *                                           id_mzc_productfields_field_value for
     *                                           checkbox/radio/select/multiselect)
     * @param int $idProductAttribute the customer's currently-selected combination
     *                                 (0 for a product with no combinations) — see
     *                                 the docblock above write()'s addCustomization
     *                                 call for why this must match exactly.
     *
     * @return array{errors: array<int,string>, id_customization: int|null, total_net_surcharge: string}
     */
    public function write(Cart $cart, int $idProduct, int $idProductAttribute, array $submittedValues, int $idLang): array
    {
        $errors = [];
        $idCustomization = null;
        $totalNetSurcharge = 0.0;

        $fields = MzcProductFieldsField::getFieldsForProduct($idProduct, $idLang);

        foreach ($fields as $fieldRow) {
            $field = new MzcProductFieldsField((int) $fieldRow['id_mzc_productfields_field']);
            $idField = (int) $field->id;
            $raw = $submittedValues[$idField] ?? null;

            $idCustomizationField = $this->synchronizer->getOrCreate($field, $idProduct);

            $isEmpty = $raw === null || $raw === '' || $raw === [];
            if ($isEmpty) {
                if ((bool) $field->required) {
                    $errors[] = $this->requiredErrorMessage($field, $idLang);
                } else {
                    $cart->deleteCustomizationToProduct($idProduct, $idCustomizationField);
                }
                continue;
            }

            [$displayValue, $netSurcharge, $fieldErrors] = in_array($field->field_type, MzcProductFieldsField::TYPES_WITH_VALUES, true)
                ? $this->resolveChoiceValue($field, $raw, $idLang, $idProduct)
                : $this->resolveFreeTextValue($field, $raw, $idProduct);

            if ($fieldErrors) {
                $errors = array_merge($errors, $fieldErrors);
                continue;
            }

            // Cart::addTextFieldToProduct() always hardcodes id_product_attribute
            // to 0 internally — it can't be used for a product with combinations.
            // Confirmed as a real, shipped bug: for any product where the
            // customer's chosen combination has a non-zero id_product_attribute,
            // the resulting `customization` row silently mismatched the
            // `cart_product` row's own id_product_attribute, which breaks the
            // LEFT JOIN inside Cart::getProducts() (`cp.id_product_attribute =
            // cu.id_product_attribute`) — id_customization then comes back empty
            // for that cart line, and Product::getPriceStatic() never adds the
            // surcharge. _addCustomization() is the same core method
            // addTextFieldToProduct() calls internally, just with the hardcoded
            // 0 replaced by the real combination id.
            $idCustomization = (int) $cart->_addCustomization(
                $idProduct,
                $idProductAttribute,
                $idCustomizationField,
                Product::CUSTOMIZE_TEXTFIELD,
                $displayValue,
                0,
                true
            );

            $this->patchCustomizedDataPrice($idCustomization, $idCustomizationField, $netSurcharge);
            $totalNetSurcharge += (float) $netSurcharge;
        }

        return [
            'errors' => $errors,
            'id_customization' => $idCustomization,
            'total_net_surcharge' => number_format($totalNetSurcharge, 6, '.', ''),
        ];
    }

    /**
     * @return array{0:string,1:string,2:array<int,string>} [displayValue, netSurcharge, errors]
     */
    private function resolveFreeTextValue(MzcProductFieldsField $field, $raw, int $idProduct): array
    {
        $value = (string) $raw;

        if ($field->max_length !== null && $field->max_length > 0 && mb_strlen($value) > (int) $field->max_length) {
            return ['', '0.000000', [sprintf('"%s": maximum length is %d characters.', $this->label($field), (int) $field->max_length)]];
        }

        $netSurcharge = (bool) $field->is_paid
            ? $this->priceCalculator->calculateNetSurcharge((string) $field->price_amount, (int) $field->price_type, $field->id_tax_rules_group, $idProduct)
            : '0.000000';

        return [$value, $netSurcharge, []];
    }

    /**
     * @return array{0:string,1:string,2:array<int,string>} [displayValue, netSurcharge, errors]
     */
    private function resolveChoiceValue(MzcProductFieldsField $field, $raw, int $idLang, int $idProduct): array
    {
        $submittedIds = array_map('intval', is_array($raw) ? $raw : [$raw]);
        $availableValues = MzcProductFieldsFieldValue::getValuesForField((int) $field->id, $idLang);
        $availableById = [];
        foreach ($availableValues as $row) {
            $availableById[(int) $row['id_mzc_productfields_field_value']] = $row;
        }

        $labels = [];
        $netSurcharge = 0.0;
        foreach ($submittedIds as $idValue) {
            if (!isset($availableById[$idValue])) {
                return ['', '0.000000', [sprintf('"%s": invalid selected option.', $this->label($field))]];
            }

            $optionRow = $availableById[$idValue];
            $labels[] = $optionRow['label'];

            if ((bool) $field->is_paid) {
                $netSurcharge += (float) $this->priceCalculator->calculateNetSurcharge(
                    (string) $optionRow['price_amount'],
                    (int) $field->price_type,
                    $optionRow['id_tax_rules_group'] !== null ? (int) $optionRow['id_tax_rules_group'] : $field->id_tax_rules_group,
                    $idProduct
                );
            }
        }

        return [implode(', ', $labels), number_format($netSurcharge, 6, '.', ''), []];
    }

    private function patchCustomizedDataPrice(int $idCustomization, int $index, string $netSurcharge): void
    {
        Db::getInstance()->update(
            'customized_data',
            [
                'price' => $netSurcharge,
                'id_module' => (int) \Module::getModuleIdByName('mzcproductfields'),
            ],
            '`id_customization` = ' . (int) $idCustomization . '
             AND `type` = ' . Product::CUSTOMIZE_TEXTFIELD . '
             AND `index` = ' . (int) $index
        );
    }

    private function requiredErrorMessage(MzcProductFieldsField $field, int $idLang): string
    {
        return sprintf('"%s" is required.', $this->label($field));
    }

    private function label(MzcProductFieldsField $field): string
    {
        $idLang = (int) \Context::getContext()->language->id;

        return is_array($field->label) ? ($field->label[$idLang] ?? '') : (string) $field->label;
    }
}
