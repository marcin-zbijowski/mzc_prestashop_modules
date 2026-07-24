<?php

declare(strict_types=1);

namespace MZConsulting\MzcProductFields\Service;

use Db;
use MZConsulting\MzcProductFields\Repository\BackgroundRepository;

/**
 * Lets a field's live-preview background image depend on the product's
 * CURRENTLY SELECTED combination (e.g. a "Canvas" print needs a different
 * mockup photo than "Paper", and "30x40cm" needs a different one than
 * "21x30cm") instead of always using one static image.
 *
 * Rules are GLOBAL, not per product: one admin-configured rule = one row in
 * `mzc_productfields_background_attribute` — a background image plus the set
 * of `attribute`.`id_attribute` values (catalog-wide, e.g. "Canvas" from a
 * Material group, "30x40cm" from a Size group) that must ALL be currently
 * selected for it to apply. A rule can require just one attribute value
 * (e.g. "21x30cm", regardless of any other attribute group) or several
 * together (e.g. "Canvas" AND "30x40cm" specifically). Since most products in
 * this catalog share the same standardized set of material/size attribute
 * values, this is configured ONCE on its own admin screen rather than
 * per-product — an earlier per-product design (one row per product +
 * attribute_ids) was scrapped because it required repeating the same mapping
 * on every product that offered "Canvas"/"30x40cm".
 *
 * Resolution (mirrored in front-product-fields.js for live, no-reload updates
 * as the customer changes their selection) always prefers the rule whose
 * attribute set is the largest subset of what's currently selected — i.e. the
 * most specific match wins. For a given product's front-office page, only
 * rules that could ever match on THAT product (all of the rule's attribute
 * ids are actually used by one of its combinations) are sent down, so a
 * product's page never carries irrelevant background images/rules from
 * completely unrelated attribute values elsewhere in the catalog.
 */
final class CombinationBackgroundResolver
{
    private BackgroundRepository $backgroundRepository;

    public function __construct(BackgroundRepository $backgroundRepository)
    {
        $this->backgroundRepository = $backgroundRepository;
    }

    /**
     * All attribute values in the shop, grouped by attribute group, for the
     * rule-creation form's multi-select.
     *
     * @return array<string,array<string,int>> group name => (value label => id_attribute)
     */
    public function getAttributeValueChoices(int $idLang): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT a.`id_attribute`, al.`name` AS attribute_name, agl.`name` AS group_name
             FROM `' . _DB_PREFIX_ . 'attribute` a
             INNER JOIN `' . _DB_PREFIX_ . 'attribute_lang` al ON al.`id_attribute` = a.`id_attribute` AND al.`id_lang` = ' . (int) $idLang . '
             INNER JOIN `' . _DB_PREFIX_ . 'attribute_group` ag ON ag.`id_attribute_group` = a.`id_attribute_group`
             INNER JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl ON agl.`id_attribute_group` = ag.`id_attribute_group` AND agl.`id_lang` = ' . (int) $idLang . '
             ORDER BY agl.`name` ASC, al.`name` ASC'
        ) ?: [];

        $choices = [];
        foreach ($rows as $row) {
            $choices[$row['group_name']][$row['attribute_name']] = (int) $row['id_attribute'];
        }

        return $choices;
    }

    /**
     * All globally-configured rules, for the admin grid.
     *
     * @return array<int,array{id:int,attribute_ids:array<int,int>,label:string,id_background:int,background_name:string,font:?string,font_name:?string,font_size:?float,pos_y:?float}>
     */
    public function getAllRules(int $idLang): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT r.`id_background_attribute`, r.`attribute_ids`, r.`id_background`, r.`font`, r.`font_size`, r.`pos_y`, b.`name` AS background_name
             FROM `' . _DB_PREFIX_ . 'mzc_productfields_background_attribute` r
             INNER JOIN `' . _DB_PREFIX_ . 'mzc_productfields_background` b ON b.`id_background` = r.`id_background`
             ORDER BY r.`id_background_attribute` ASC'
        ) ?: [];

        if (!$rows) {
            return [];
        }

        $labelsByAttributeId = $this->getAttributeLabelsById($idLang);
        $fontNamesByFamily = $this->getFontNamesByFamily();

        $result = [];
        foreach ($rows as $row) {
            $attributeIds = array_map('intval', explode(',', (string) $row['attribute_ids']));
            $labels = array_map(
                static fn ($id) => $labelsByAttributeId[$id] ?? ('#' . $id),
                $attributeIds
            );

            $result[] = [
                'id' => (int) $row['id_background_attribute'],
                'attribute_ids' => $attributeIds,
                'label' => implode(' + ', $labels),
                'id_background' => (int) $row['id_background'],
                'background_name' => (string) $row['background_name'],
                'font' => $row['font'] ?: null,
                'font_name' => $row['font'] ? ($fontNamesByFamily[$row['font']] ?? $row['font']) : null,
                'font_size' => $row['font_size'] !== null ? (float) $row['font_size'] : null,
                'pos_y' => $row['pos_y'] !== null ? (float) $row['pos_y'] : null,
            ];
        }

        return $result;
    }

    /**
     * A single rule's raw stored values, for pre-filling the edit form — see
     * BackgroundRuleController::indexAction()'s `edit_id` handling. Unlike
     * getAllRules(), attribute ids aren't resolved to labels here since the
     * form's multiselect needs the raw ids back to pre-select the right
     * options.
     *
     * @return array{id:int,attribute_ids:array<int,int>,id_background:int,font:?string,font_size:?float,pos_y:?float}|null
     */
    public function getRuleById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = Db::getInstance()->getRow(
            'SELECT `id_background_attribute`, `attribute_ids`, `id_background`, `font`, `font_size`, `pos_y`
             FROM `' . _DB_PREFIX_ . 'mzc_productfields_background_attribute`
             WHERE `id_background_attribute` = ' . (int) $id
        );

        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row['id_background_attribute'],
            'attribute_ids' => array_map('intval', explode(',', (string) $row['attribute_ids'])),
            'id_background' => (int) $row['id_background'],
            'font' => $row['font'] ?: null,
            'font_size' => $row['font_size'] !== null ? (float) $row['font_size'] : null,
            'pos_y' => $row['pos_y'] !== null ? (float) $row['pos_y'] : null,
        ];
    }

    /**
     * Updates an existing rule in place instead of leaving it to be found (or
     * not) by its old attribute-id key — needed because editing a rule can
     * itself change its attribute selection, at which point the old row would
     * no longer share a key with the new values and saveRule()'s delete-by-key
     * would miss it entirely, leaving a stale duplicate behind. Deleting the
     * known row by id first, then delegating to saveRule() for the
     * insert (+ any now-conflicting key collision), keeps that one rule's
     * identity stable for the merchant while reusing the existing
     * create/overwrite logic.
     *
     * @param array<int,int> $attributeIds
     */
    public function updateRule(int $id, array $attributeIds, int $idBackground, ?string $font, ?float $fontSize, ?float $posY): bool
    {
        if ($id <= 0) {
            return false;
        }

        Db::getInstance()->delete('mzc_productfields_background_attribute', '`id_background_attribute` = ' . (int) $id);

        return $this->saveRule($attributeIds, $idBackground, $font, $fontSize, $posY);
    }

    /**
     * Creates or replaces the rule for a given set of attribute ids (a rule is
     * uniquely identified by its sorted attribute-id set — picking the same
     * combination again just changes which background/font/position it points
     * to).
     *
     * @param array<int,int> $attributeIds
     */
    public function saveRule(array $attributeIds, int $idBackground, ?string $font, ?float $fontSize, ?float $posY): bool
    {
        $attributeIds = array_values(array_unique(array_map('intval', $attributeIds)));
        sort($attributeIds);
        if (!$attributeIds || $idBackground <= 0) {
            return false;
        }

        $key = implode(',', $attributeIds);

        Db::getInstance()->delete('mzc_productfields_background_attribute', '`attribute_ids` = "' . pSQL($key) . '"');

        // Db::insert()'s 3rd arg ($null_values) MUST be true here: left
        // false (its default), a PHP null gets interpolated as an empty
        // string ('') rather than the SQL keyword NULL (see
        // classes/db/Db.php's insert(), line ~464), and MySQL then silently
        // coerces that '' into 0 for font_size/pos_y's DECIMAL columns —
        // confirmed as a real, shipped bug: a rule saved with font/size/
        // position left blank stored 0 instead of "no override", which then
        // made front-product-fields.js's effectivePosY() (a strict
        // !== null check, since 0% is itself a valid override value) force
        // every such rule's text to the very top of the image instead of
        // falling back to the field's own default position.
        return Db::getInstance()->insert('mzc_productfields_background_attribute', [
            'attribute_ids' => pSQL($key),
            'id_background' => $idBackground,
            'font' => $font ?: null,
            'font_size' => $fontSize,
            'pos_y' => $posY,
        ], true);
    }

    public function deleteRule(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        return Db::getInstance()->delete('mzc_productfields_background_attribute', '`id_background_attribute` = ' . (int) $id);
    }

    /**
     * Resolved rules for a product's front-office page, most specific (most
     * attribute ids) first — front-product-fields.js applies the same "most
     * specific match among currently selected attributes wins" logic
     * client-side, since the customer can change their selection without a
     * page reload. Only rules whose attribute ids are ALL actually used by
     * one of this product's own combinations are returned, since any other
     * rule could never match here anyway.
     *
     * @return array<int,array{attribute_ids:array<int,int>,id_background:int,url:string,font:?string,font_size:?float,pos_y:?float}>
     */
    public function getResolvedRulesForFrontend(int $idProduct): array
    {
        $productAttributeIds = $this->getProductAttributeIdUniverse($idProduct);
        if (!$productAttributeIds) {
            return [];
        }

        $rows = Db::getInstance()->executeS(
            'SELECT `attribute_ids`, `id_background`, `font`, `font_size`, `pos_y` FROM `' . _DB_PREFIX_ . 'mzc_productfields_background_attribute`'
        ) ?: [];

        $rules = [];
        foreach ($rows as $row) {
            $attributeIds = array_map('intval', explode(',', (string) $row['attribute_ids']));
            if (array_diff($attributeIds, $productAttributeIds)) {
                // At least one attribute id in this rule can never be
                // selected on this product — it could never match here.
                continue;
            }

            $url = $this->backgroundRepository->getUrl((int) $row['id_background']);
            if ($url === null) {
                continue;
            }

            $rules[] = [
                'attribute_ids' => $attributeIds,
                'id_background' => (int) $row['id_background'],
                'url' => $url,
                // Null means "no override — use the field's own preview_font/
                // preview_font_size" (see front-product-fields.js's own
                // resolveBoxForEntry()/repositionAll() docblocks for how this
                // gets applied): a rule only needs to set these when the
                // mockup image it points to genuinely needs a different look
                // than the field's own default (e.g. a rustic font for a
                // canvas mockup vs. a modern one for a black frame).
                'font' => $row['font'] ?: null,
                'font_size' => $row['font_size'] !== null ? (float) $row['font_size'] : null,
                // Same "null = no override" convention as font/font_size —
                // see front-product-fields.js's effectivePosY(), which falls
                // back to the field's own preview_pos_y when this is null.
                'pos_y' => $row['pos_y'] !== null ? (float) $row['pos_y'] : null,
            ];
        }

        usort($rules, static fn ($a, $b) => count($b['attribute_ids']) <=> count($a['attribute_ids']));

        return $rules;
    }

    /**
     * @return array<int,int> distinct id_attribute values used across any of this product's combinations
     */
    private function getProductAttributeIdUniverse(int $idProduct): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT DISTINCT pac.`id_attribute`
             FROM `' . _DB_PREFIX_ . 'product_attribute` pa
             INNER JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON pac.`id_product_attribute` = pa.`id_product_attribute`
             WHERE pa.`id_product` = ' . (int) $idProduct
        ) ?: [];

        return array_map(static fn ($row) => (int) $row['id_attribute'], $rows);
    }

    /**
     * @return array<int,string> id_attribute => "value name (group name)"
     */
    private function getAttributeLabelsById(int $idLang): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT a.`id_attribute`, al.`name` AS attribute_name, agl.`name` AS group_name
             FROM `' . _DB_PREFIX_ . 'attribute` a
             INNER JOIN `' . _DB_PREFIX_ . 'attribute_lang` al ON al.`id_attribute` = a.`id_attribute` AND al.`id_lang` = ' . (int) $idLang . '
             INNER JOIN `' . _DB_PREFIX_ . 'attribute_group` ag ON ag.`id_attribute_group` = a.`id_attribute_group`
             INNER JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl ON agl.`id_attribute_group` = ag.`id_attribute_group` AND agl.`id_lang` = ' . (int) $idLang
        ) ?: [];

        $labels = [];
        foreach ($rows as $row) {
            $labels[(int) $row['id_attribute']] = $row['attribute_name'] . ' (' . $row['group_name'] . ')';
        }

        return $labels;
    }

    /**
     * @return array<string,string> font_family => display name, for the admin grid
     */
    private function getFontNamesByFamily(): array
    {
        $names = [];
        foreach (\MzcProductFieldsFont::listAll() as $row) {
            $names[$row['font_family']] = $row['name'];
        }

        return $names;
    }
}
