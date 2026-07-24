<?php

declare(strict_types=1);

namespace MZConsulting\MzcProductFields\Service;

use Configuration;
use Db;
use Language;
use MzcProductFieldsField;

/**
 * One-way, re-runnable migrator from Anvanto Product Fields (`an_productfields`)
 * into this module's own schema. Read-only against Anvanto's tables — never
 * writes to or deletes from them (see the plan's hard constraint: Anvanto may
 * only be uninstalled after an explicit, separately-confirmed backup).
 *
 * Two independent steps, matching the plan's ordering:
 * 1. archiveOrders() — copies historical `an_productfields_order`(+`_lang`)
 *    rows into `mzc_productfields_order_archive`(+`_lang`), which this
 *    module's own uninstall() never touches. Must run before anyone considers
 *    uninstalling Anvanto (its own sql/uninstall.php DROPs those tables).
 * 2. migrateFieldConfig() — creates our own field definitions from Anvanto's
 *    field config, scoped to one category's products at a time (the plan's
 *    "pilot category first" requirement) rather than migrating the whole
 *    catalog in one pass.
 *
 * Both steps are idempotent: re-running only processes rows/fields not
 * already migrated (tracked via `source_an_field_id` on our own field table,
 * and via a natural-key existence check on the order archive).
 */
class AnvantoMigrator
{
    public function isAnvantoDataAvailable(): bool
    {
        return $this->tableExists('an_productfields_fields') && $this->tableExists('an_productfields_order');
    }

    /**
     * @return array{available:bool, inserted:int, skipped:int, total_source:int}
     */
    public function archiveOrders(): array
    {
        if (!$this->tableExists('an_productfields_order')) {
            return ['available' => false, 'inserted' => 0, 'skipped' => 0, 'total_source' => 0];
        }

        $idLangDefault = (int) Configuration::get('PS_LANG_DEFAULT');
        $langIds = array_map(static fn ($lang) => (int) $lang['id_lang'], Language::getLanguages(false));

        $sourceRows = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'an_productfields_order` ORDER BY `id_pf_order` ASC'
        ) ?: [];

        $inserted = 0;
        $skipped = 0;

        foreach ($sourceRows as $row) {
            if ($this->orderRowAlreadyArchived($row)) {
                ++$skipped;

                continue;
            }

            $archive = new \MzcProductFieldsOrderArchive();
            $archive->source_id_field = (int) $row['id_field'] ?: null;
            $archive->source_pf_order_id = (int) $row['id_pf_order'];
            $archive->source = 'an_productfields';
            $archive->id_cart = (int) $row['id_cart'];
            $archive->id_order = (int) $row['id_order'];
            $archive->id_order_detail = (int) $row['id_order_detail'];
            $archive->id_product = (int) $row['id_product'];
            $archive->id_product_attribute = (int) $row['id_product_attribute'];
            $archive->id_customization = (int) $row['id_customization'];
            $archive->qty = (int) $row['qty'];
            $archive->price = number_format((float) $row['price'], 6, '.', '');
            $archive->price_wt = number_format((float) $row['price_wt'], 6, '.', '');
            $archive->date_add = (string) $row['date_add'];
            $archive->add();

            $langRows = Db::getInstance()->executeS(
                'SELECT * FROM `' . _DB_PREFIX_ . 'an_productfields_order_lang`
                 WHERE `id_pf_order` = ' . (int) $row['id_pf_order']
            ) ?: [];

            $byLang = [];
            foreach ($langRows as $langRow) {
                $byLang[(int) $langRow['id_lang']] = $langRow;
            }
            $fallback = $byLang[$idLangDefault] ?? (reset($byLang) ?: null);

            $snapshot = [];
            foreach ($langIds as $idLang) {
                $source = $byLang[$idLang] ?? $fallback;
                $snapshot[$idLang] = [
                    'title' => $source['title'] ?? '',
                    'title_value' => $source['title_value'] ?? '',
                ];
            }
            $archive->saveLangData($snapshot);

            ++$inserted;
        }

        return [
            'available' => true,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'total_source' => count($sourceRows),
        ];
    }

    /**
     * @return array{available:bool, category_name:string, created:int, skipped_existing:int, warnings:array<int,string>, created_field_ids:array<int,int>}
     */
    public function migrateFieldConfig(int $idCategory): array
    {
        if (!$this->tableExists('an_productfields_fields')) {
            return [
                'available' => false,
                'category_name' => '',
                'created' => 0,
                'skipped_existing' => 0,
                'warnings' => [],
                'created_field_ids' => [],
            ];
        }

        $category = new \Category($idCategory, (int) Configuration::get('PS_LANG_DEFAULT'));
        $categoryName = \Validate::isLoadedObject($category) ? (string) $category->name : ('#' . $idCategory);

        $productIds = $this->getCategoryProductIds($idCategory);

        $matchedFieldIds = $this->findMatchedAnvantoFieldIds($idCategory, $productIds);

        $created = 0;
        $skippedExisting = 0;
        $warnings = [];
        $createdFieldIds = [];

        foreach ($matchedFieldIds as $anFieldId) {
            if ($this->alreadyMigrated($anFieldId)) {
                ++$skippedExisting;

                continue;
            }

            $anField = Db::getInstance()->getRow(
                'SELECT * FROM `' . _DB_PREFIX_ . 'an_productfields_fields` WHERE `id_field` = ' . (int) $anFieldId
            );
            if (!$anField || !(int) $anField['active']) {
                continue;
            }

            if (!in_array($anField['type'], MzcProductFieldsField::FIELD_TYPES, true)) {
                $warnings[] = sprintf('Field #%d: unsupported type "%s", skipped.', $anFieldId, $anField['type']);

                continue;
            }

            if ((int) $anField['type_price'] === 1 && (float) $anField['price_percent'] !== 0.0) {
                $warnings[] = sprintf(
                    'Field #%d: percent-based pricing (%s%%) is not supported here, migrated as free — set its price manually if needed.',
                    $anFieldId,
                    $anField['price_percent']
                );
            }

            $relations = Db::getInstance()->executeS(
                'SELECT * FROM `' . _DB_PREFIX_ . 'an_productfields_fields_relations` WHERE `id_field` = ' . (int) $anFieldId
            ) ?: [];

            $field = $this->buildField($anField, $relations, $idCategory);
            $field->add();

            if ((int) $field->scope_type === MzcProductFieldsField::SCOPE_PRODUCTS) {
                $this->linkProducts((int) $field->id, $relations);
            }

            ++$created;
            $createdFieldIds[] = (int) $field->id;
        }

        return [
            'available' => true,
            'category_name' => $categoryName,
            'created' => $created,
            'skipped_existing' => $skippedExisting,
            'warnings' => $warnings,
            'created_field_ids' => $createdFieldIds,
        ];
    }

    private function buildField(array $anField, array $relations, int $idCategory): MzcProductFieldsField
    {
        $field = new MzcProductFieldsField();
        $field->source_an_field_id = (int) $anField['id_field'];
        $field->field_type = (string) $anField['type'];
        $field->required = (int) $anField['required'];
        $field->max_length = ((int) $anField['max_length']) ?: null;
        $field->char_counter = (int) $anField['char_counter'];
        $field->position = (int) $anField['position'];
        $field->active = 1;
        $field->preview_font_size = '6.00';

        $hasCategoryRelation = false;
        $hasAllRelation = false;
        foreach ($relations as $relation) {
            if ((int) $relation['type'] === 1 && (int) $relation['id_type'] === $idCategory) {
                $hasCategoryRelation = true;
            }
            if ((int) $relation['type'] === 0) {
                $hasAllRelation = true;
            }
        }

        if ($hasAllRelation) {
            $field->scope_type = MzcProductFieldsField::SCOPE_ALL;
            $field->scope_id = null;
        } elseif ($hasCategoryRelation) {
            $field->scope_type = MzcProductFieldsField::SCOPE_CATEGORY;
            $field->scope_id = $idCategory;
        } else {
            $field->scope_type = MzcProductFieldsField::SCOPE_PRODUCTS;
            $field->scope_id = null;
        }

        $price = (float) $anField['price'];
        $field->is_paid = $price > 0.0 ? 1 : 0;
        $field->price_type = MzcProductFieldsField::PRICE_TYPE_NET;
        $field->price_amount = number_format($price, 6, '.', '');
        $field->id_tax_rules_group = ((int) $anField['custom_tax']) ?: null;

        $idLangDefault = (int) Configuration::get('PS_LANG_DEFAULT');
        $langRow = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'an_productfields_fields_lang`
             WHERE `id_field` = ' . (int) $anField['id_field'] . ' AND `id_lang` = ' . $idLangDefault
        );

        $label = trim((string) ($langRow['title'] ?? '')) ?: ('Field #' . $anField['id_field']);
        $placeholder = trim((string) ($langRow['placeholder'] ?? ''));

        foreach (Language::getLanguages(false) as $lang) {
            $idLang = (int) $lang['id_lang'];
            $field->label[$idLang] = $label;
            $field->placeholder[$idLang] = $placeholder;
        }

        return $field;
    }

    private function linkProducts(int $idField, array $relations): void
    {
        foreach ($relations as $relation) {
            if ((int) $relation['type'] !== 2) {
                continue;
            }

            $idProduct = (int) $relation['id_type'];
            if ($idProduct <= 0) {
                continue;
            }

            Db::getInstance()->insert('mzc_productfields_field_product', [
                'id_mzc_productfields_field' => $idField,
                'id_product' => $idProduct,
            ]);
        }
    }

    /**
     * @return array<int,int> ids of Anvanto products in this category
     */
    private function getCategoryProductIds(int $idCategory): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'category_product` WHERE `id_category` = ' . (int) $idCategory
        ) ?: [];

        return array_map(static fn ($row) => (int) $row['id_product'], $rows);
    }

    /**
     * Active Anvanto field ids whose relations apply to this category: either
     * directly (type=1 relation on this category), via one of its products
     * (type=2 relation), or store-wide (type=0, "all products" — included
     * since such a field necessarily also applies here).
     *
     * @param array<int,int> $productIds
     *
     * @return array<int,int>
     */
    private function findMatchedAnvantoFieldIds(int $idCategory, array $productIds): array
    {
        $conditions = ['(r.`type` = 1 AND r.`id_type` = ' . (int) $idCategory . ')'];
        $conditions[] = '(r.`type` = 0 AND r.`id_type` = 0)';
        if ($productIds) {
            $conditions[] = '(r.`type` = 2 AND r.`id_type` IN (' . implode(',', $productIds) . '))';
        }

        $rows = Db::getInstance()->executeS(
            'SELECT DISTINCT r.`id_field`
             FROM `' . _DB_PREFIX_ . 'an_productfields_fields_relations` r
             INNER JOIN `' . _DB_PREFIX_ . 'an_productfields_fields` f ON f.`id_field` = r.`id_field`
             WHERE f.`active` = 1 AND (' . implode(' OR ', $conditions) . ')'
        ) ?: [];

        return array_map(static fn ($row) => (int) $row['id_field'], $rows);
    }

    private function alreadyMigrated(int $anFieldId): bool
    {
        $row = Db::getInstance()->getRow(
            'SELECT `id_mzc_productfields_field` FROM `' . _DB_PREFIX_ . 'mzc_productfields_field`
             WHERE `source_an_field_id` = ' . (int) $anFieldId
        );

        return (bool) $row;
    }

    private function orderRowAlreadyArchived(array $row): bool
    {
        $existing = Db::getInstance()->getRow(
            'SELECT `id_mzc_productfields_order_archive` FROM `' . _DB_PREFIX_ . 'mzc_productfields_order_archive`
             WHERE `source_pf_order_id` = ' . (int) $row['id_pf_order']
        );

        return (bool) $existing;
    }

    private function tableExists(string $table): bool
    {
        $rows = Db::getInstance()->executeS('SHOW TABLES LIKE "' . pSQL(_DB_PREFIX_ . $table) . '"');

        return (bool) $rows;
    }
}
