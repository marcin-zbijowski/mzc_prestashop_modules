<?php

declare(strict_types=1);

namespace MZConsulting\MzcProductFields\Service;

use Context;
use MZConsulting\MzcProductFields\Repository\BackgroundRepository;
use MzcProductFieldsField;
use MzcProductFieldsFieldValue;
use MzcProductFieldsFont;
use Tools;

/**
 * Prepares the field list for a product's front-office rendering
 * (displayProductAdditionalInfo hook): resolves each field's applicable
 * values (for select/radio/checkbox/multiselect) and its display price
 * (the true gross impact — see CustomizationPriceCalculator::calculateDisplayGross()'s
 * docblock for why that's a different figure than what gets stored), collects
 * the uploaded fonts actually referenced so the hook only emits @font-face
 * declarations it needs, and attaches each preview background image to
 * render right after whichever field the customer types into last (see
 * attachBackgroundsToFields()) — fields sharing one background (e.g.
 * "Front"/"Back" of a product) render it once, right after the last of
 * that group, rather than bunching every background after every field.
 */
final class ProductFieldsPresenter
{
    private CustomizationPriceCalculator $priceCalculator;
    private BackgroundRepository $backgroundRepository;
    private CombinationBackgroundResolver $combinationBackgroundResolver;

    public function __construct(
        CustomizationPriceCalculator $priceCalculator,
        BackgroundRepository $backgroundRepository,
        CombinationBackgroundResolver $combinationBackgroundResolver
    ) {
        $this->priceCalculator = $priceCalculator;
        $this->backgroundRepository = $backgroundRepository;
        $this->combinationBackgroundResolver = $combinationBackgroundResolver;
    }

    /**
     * @return array{
     *     fields: array<int,array>,
     *     fonts: array<int,array{font_family:string,url:string}>,
     *     backgrounds: array<int,array{id_background:int,url:string,name:string,fields:array<int,int>}>,
     *     combination_rules: array<int,array{attribute_ids:array<int,int>,url:string}>
     * }
     */
    public function present(int $idProduct, int $idLang): array
    {
        $rows = MzcProductFieldsField::getFieldsForProduct($idProduct, $idLang);
        if (!$rows) {
            return ['fields' => [], 'fonts' => [], 'backgrounds' => [], 'combination_rules' => []];
        }

        $fontFamiliesUsed = [];
        $backgroundsUsed = [];
        $fields = [];

        foreach ($rows as $row) {
            $idField = (int) $row['id_mzc_productfields_field'];
            $isPaid = (bool) $row['is_paid'];
            $priceType = (int) $row['price_type'];
            $fieldTaxGroup = $row['id_tax_rules_group'];
            $isFrontField = (bool) $row['is_front_field'];
            // A front field's id_background (if any is still on the row —
            // e.g. left over from before this field was switched to Front)
            // is never consulted: nulling it out here, at the source, keeps
            // every downstream consumer (attachBackgroundsToFields() below,
            // the front-office template's data-mzc-preview-bg attribute, the
            // JS's own entry.staticBox) from ever having to special-case it
            // separately — a front field simply never carries a background
            // reference past this point.
            $idBackground = (!$isFrontField && $row['id_background'] !== null) ? (int) $row['id_background'] : null;

            $presented = [
                'id' => $idField,
                'type' => $row['field_type'],
                'label' => $row['label'],
                'placeholder' => $row['placeholder'],
                'required' => (bool) $row['required'],
                'max_length' => $row['max_length'] !== null ? (int) $row['max_length'] : null,
                'char_counter' => (bool) $row['char_counter'],
                'is_paid' => $isPaid,
                'display_price' => $isPaid
                    ? $this->formatPrice($this->priceCalculator->calculateDisplayGross((string) $row['price_amount'], $priceType, $fieldTaxGroup, $idProduct))
                    : null,
                // Default to dead-center rather than leaving unset: the JS
                // treats a missing position as "nothing to preview at all"
                // (filters the field out entirely — see its initContainer()),
                // which silently hid the preview for every field an admin
                // hadn't yet positioned (all Anvanto-migrated fields, since
                // Anvanto's own area_x/y were never carried over — see
                // AnvantoMigrator's docblock). Same reasoning as
                // preview_font_size's existing 6.0 default below.
                'preview_pos_x' => $row['preview_pos_x'] !== null ? (float) $row['preview_pos_x'] : 50.0,
                'preview_pos_y' => $row['preview_pos_y'] !== null ? (float) $row['preview_pos_y'] : 50.0,
                'preview_font' => $row['preview_font'] ?: null,
                'preview_font_size' => $row['preview_font_size'] !== null ? (float) $row['preview_font_size'] : 6.0,
                // Null (not a numeric default) means "no cap" — the JS/snapshot
                // code falls back to the full available width, exactly the
                // pre-existing behaviour, when this isn't set.
                'preview_max_width' => $row['preview_max_width'] !== null ? (float) $row['preview_max_width'] : null,
                'preview_color' => $row['preview_color'] ?: '#000000',
                'id_background' => $idBackground,
                'is_front_field' => $isFrontField,
                'values' => [],
            ];

            if ($row['preview_font']) {
                $fontFamiliesUsed[$row['preview_font']] = true;
            }

            if ($idBackground !== null) {
                $backgroundsUsed[$idBackground][] = $idField;
            }

            if (in_array($row['field_type'], MzcProductFieldsField::TYPES_WITH_VALUES, true)) {
                foreach (MzcProductFieldsFieldValue::getValuesForField($idField, $idLang) as $valueRow) {
                    $presented['values'][] = [
                        'id' => (int) $valueRow['id_mzc_productfields_field_value'],
                        'label' => $valueRow['label'],
                        'display_price' => $isPaid
                            ? $this->formatPrice($this->priceCalculator->calculateDisplayGross(
                                (string) $valueRow['price_amount'],
                                $priceType,
                                $valueRow['id_tax_rules_group'] ?? $fieldTaxGroup,
                                $idProduct
                            ))
                            : null,
                    ];
                }
            }

            $fields[] = $presented;
        }

        $combinationRules = $this->combinationBackgroundResolver->getResolvedRulesForFrontend($idProduct);
        foreach ($combinationRules as $rule) {
            if (!isset($backgroundsUsed[$rule['id_background']])) {
                $backgroundsUsed[$rule['id_background']] = [];
            }
        }

        $backgrounds = $this->resolveBackgrounds($backgroundsUsed);
        $dynamicBackgroundIds = array_column($combinationRules, 'id_background');
        $fields = $this->attachBackgroundsToFields($fields, $backgrounds, array_flip($dynamicBackgroundIds));

        return [
            'fields' => $fields,
            'fonts' => $this->resolveFonts(array_keys($fontFamiliesUsed)),
            'backgrounds' => $backgrounds,
            'combination_rules' => $combinationRules,
        ];
    }

    /**
     * Each background renders immediately after the LAST non-front field
     * that could actually show a preview on it, so it appears right below
     * whichever field the customer is filling in, instead of bunched
     * together after every field on the page (including ones that can never
     * preview anything at all — see below).
     *
     * A field only ever gets a preview node client-side if it has a preview
     * position set (front-product-fields.js filters out anything without
     * one — see its initContainer()), so a field missing preview_pos_x/y is
     * never a valid placement target here. A FRONT field (is_front_field —
     * see MzcProductFieldsField's docblock) is never a candidate either, full
     * stop, regardless of position or any stale id_background it might still
     * carry: it always floats on the product's own photo, so attaching a
     * background box near it would be actively misleading. Only non-front
     * fields are ever considered, and among those: one that statically owns
     * this exact background is always the target; failing that, the LAST
     * non-front positioned field on the page (dynamicMode is a per-product
     * switch — once active, EVERY non-front positioned field routes into
     * whichever box the current rule points to, not just the one that
     * happens to statically own it).
     *
     * Before is_front_field existed, this had to guess which fields were
     * "background-eligible" from incidental signals (did it own some other
     * background, was it first in the list) — confirmed unreliable twice
     * over: a field with no static background at all (relying purely on a
     * combination rule) wouldn't match any heuristic, and worse, a genuinely
     * front-facing field on the same product as a back-facing one could get
     * swept into the back field's box the moment a rule matched, since
     * nothing distinguished the two. is_front_field is what actually
     * distinguishes them now.
     *
     * @param array<int,array> $fields
     * @param array<int,array{id_background:int,url:string,fields:array<int,int>}> $backgrounds
     * @param array<int,bool> $dynamicBackgroundIds id_background => true for every background referenced by a combination rule
     *
     * @return array<int,array>
     */
    private function attachBackgroundsToFields(array $fields, array $backgrounds, array $dynamicBackgroundIds): array
    {
        foreach ($fields as &$field) {
            $field['backgrounds_after'] = [];
        }
        unset($field);

        if (!$fields) {
            return $fields;
        }

        $isCandidate = static function (array $field): bool {
            return !$field['is_front_field']
                && $field['preview_pos_x'] !== null
                && $field['preview_pos_y'] !== null;
        };

        // Absolute last resort, only reachable if not one field on the whole
        // product is a positioned non-front field — in which case there's no
        // principled placement anyway, so just avoid a fatal on the array
        // access below.
        $lastFieldIndex = count($fields) - 1;

        $hasAnyDynamicRule = (bool) $dynamicBackgroundIds;

        foreach ($backgrounds as $background) {
            $staticOwnerIds = array_flip($background['fields']);
            $isDynamicBackground = isset($dynamicBackgroundIds[$background['id_background']]);

            // A non-front field that STATICALLY owns this exact background
            // (its own "Preview background image" points here) always wins
            // the placement, even when the same background is ALSO
            // referenced by a combination rule elsewhere. Failing that, for
            // a dynamic background, the LAST non-front positioned field on
            // the page wins (see this method's own docblock for why "last").
            $targetIndex = null;
            $lastDynamicIndex = null;
            foreach ($fields as $index => $field) {
                if (!$isCandidate($field)) {
                    continue;
                }

                if (isset($staticOwnerIds[$field['id']])) {
                    $targetIndex = $index;
                } elseif ($isDynamicBackground && $hasAnyDynamicRule) {
                    $lastDynamicIndex = $index;
                }
            }

            $fields[$targetIndex ?? $lastDynamicIndex ?? $lastFieldIndex]['backgrounds_after'][] = $background;
        }

        return $fields;
    }

    private function formatPrice(string $amount): string
    {
        $context = Context::getContext();
        $converted = Tools::convertPrice((float) $amount, $context->currency);

        return $context->getCurrentLocale()->formatPrice($converted, $context->currency->iso_code);
    }

    /**
     * @param array<int,string> $fontFamilies
     *
     * @return array<int,array{font_family:string,url:string}>
     */
    private function resolveFonts(array $fontFamilies): array
    {
        if (!$fontFamilies) {
            return [];
        }

        $fontFamilies = array_flip($fontFamilies);
        $fonts = [];
        $baseUrl = rtrim(Context::getContext()->shop->getBaseURL(true), '/') . '/modules/mzcproductfields/views/fonts/uploads/';

        foreach (MzcProductFieldsFont::listAll() as $row) {
            if (isset($fontFamilies[$row['font_family']])) {
                $fonts[] = [
                    'font_family' => $row['font_family'],
                    'url' => $baseUrl . $row['filename'],
                ];
            }
        }

        return $fonts;
    }

    /**
     * @param array<int,array<int,int>> $backgroundsUsed id_background => field ids assigned to it
     *
     * @return array<int,array{id_background:int,url:string,name:string,fields:array<int,int>}>
     */
    private function resolveBackgrounds(array $backgroundsUsed): array
    {
        if (!$backgroundsUsed) {
            return [];
        }

        $backgrounds = [];
        foreach ($backgroundsUsed as $idBackground => $fieldIds) {
            $url = $this->backgroundRepository->getUrl($idBackground);
            if ($url === null) {
                // The field still references an id_background that was since
                // deleted — fields fall back to the no-background (floating
                // overlay on the product photo) behaviour.
                continue;
            }

            $backgrounds[] = [
                'id_background' => $idBackground,
                'url' => $url,
                'fields' => $fieldIds,
            ];
        }

        return $backgrounds;
    }
}
