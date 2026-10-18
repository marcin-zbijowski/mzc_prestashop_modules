<?php

declare(strict_types=1);

namespace MZConsulting\MzcProductFields\Repository;

use Category;
use Context;
use Db;
use MzcProductFieldsField;
use Product;
use Validate;

/**
 * Bridges the legacy `MzcProductFieldsField` ObjectModel and the Symfony
 * form layer for the field CRUD screen. Both sides key multilang data by
 * id_lang (int) — PrestaShop's `TranslatableType` builds its children via
 * `$builder->add($locale['id_lang'], ...)` (confirmed in core source), so no
 * id_lang/locale conversion is needed here.
 *
 * scope_type = SCOPE_CATEGORY uses the single `scope_id` column (one
 * category). scope_type = SCOPE_PRODUCTS uses the many-to-many
 * `mzc_productfields_field_product` table instead, since a field can be
 * assigned to one or many individually chosen products.
 *
 * `category_id`/`products_json` are plain hidden form fields written by this
 * module's own JS widget (views/js/admin-field-form.js) — see FieldType's
 * docblock for why this doesn't use PrestaShop core's search-and-select form
 * types.
 */
final class FieldRepository
{
    /**
     * @return array<int,array> raw rows for the admin grid, in the given language
     */
    public function findAllForGrid(int $idLang): array
    {
        return MzcProductFieldsField::listAll($idLang);
    }

    /**
     * @return array form-shaped data for a given field id, or defaults for a new field (id <= 0)
     */
    public function getFormData(int $id): array
    {
        $field = $id > 0 ? new MzcProductFieldsField($id) : new MzcProductFieldsField();

        $scopeType = $field->id ? (int) $field->scope_type : MzcProductFieldsField::SCOPE_PRODUCTS;

        return [
            'id' => (int) $field->id,
            'label' => is_array($field->label) ? $field->label : [],
            'placeholder' => is_array($field->placeholder) ? $field->placeholder : [],
            'field_type' => $field->field_type ?: MzcProductFieldsField::TYPE_TEXT,
            'scope_type' => $scopeType,
            'category_id' => $scopeType === MzcProductFieldsField::SCOPE_CATEGORY && $field->scope_id
                ? (string) $field->scope_id
                : '',
            'products_json' => $field->id
                ? json_encode($this->getProductIdsForField((int) $field->id))
                : '[]',
            'required' => (int) $field->required,
            'is_paid' => (int) $field->is_paid,
            'price_type' => (int) $field->price_type,
            'price_amount' => $field->price_amount !== null ? (float) $field->price_amount : 0.0,
            'id_tax_rules_group' => $field->id_tax_rules_group !== null ? (int) $field->id_tax_rules_group : null,
            'max_length' => $field->max_length !== null ? (int) $field->max_length : null,
            'char_counter' => (int) $field->char_counter,
            'position' => (int) $field->position,
            'active' => $field->id ? (bool) $field->active : true,
            'preview_pos_x' => $field->preview_pos_x !== null ? (float) $field->preview_pos_x : null,
            'preview_pos_y' => $field->preview_pos_y !== null ? (float) $field->preview_pos_y : null,
            'preview_font' => $field->preview_font ?: null,
            'preview_font_size' => $field->preview_font_size !== null ? (float) $field->preview_font_size : 6.0,
            'preview_max_width' => $field->preview_max_width !== null ? (float) $field->preview_max_width : null,
            'preview_color' => $field->preview_color ?: '#000000',
            'id_background' => $field->id_background !== null ? (int) $field->id_background : null,
            'is_front_field' => $field->id ? (bool) $field->is_front_field : true,
        ];
    }

    /**
     * Display-only data (id + name + image) for pre-filling the JS widget's
     * initial "selected" chips — kept separate from getFormData() since it's
     * not part of what the Symfony form itself submits.
     *
     * @return array{category: array{id:int,name:string}|null, products: array<int,array{id:int,name:string,image:string}>}
     */
    public function getPrefillData(int $id): array
    {
        if ($id <= 0) {
            return ['category' => null, 'products' => []];
        }

        $field = new MzcProductFieldsField($id);
        $idLang = (int) Context::getContext()->language->id;

        $category = null;
        if ((int) $field->scope_type === MzcProductFieldsField::SCOPE_CATEGORY && $field->scope_id) {
            $entries = $this->formatCategoryEntries([(int) $field->scope_id]);
            $category = $entries[0] ?? null;
        }

        return [
            'category' => $category,
            'products' => $this->getProductEntriesForField($id, $idLang),
        ];
    }

    /**
     * Persist form-shaped data (see getFormData()) as a MzcProductFieldsField row.
     *
     * @return int the saved field's id
     */
    public function save(array $data): int
    {
        $id = (int) ($data['id'] ?? 0);
        $field = $id > 0 ? new MzcProductFieldsField($id) : new MzcProductFieldsField();

        $scopeType = (int) $data['scope_type'];

        $field->label = $data['label'] ?? [];
        $field->placeholder = $data['placeholder'] ?? [];
        $field->field_type = (string) $data['field_type'];
        $field->scope_type = $scopeType;
        $field->scope_id = $scopeType === MzcProductFieldsField::SCOPE_CATEGORY && !empty($data['category_id'])
            ? (int) $data['category_id']
            : null;
        $field->required = (int) $data['required'];
        $field->is_paid = (int) $data['is_paid'];
        $field->price_type = (int) $data['price_type'];
        $field->price_amount = number_format((float) $data['price_amount'], 6, '.', '');
        $field->id_tax_rules_group = ($data['id_tax_rules_group'] ?? null) !== null ? (int) $data['id_tax_rules_group'] : null;
        $field->max_length = ($data['max_length'] ?? null) !== null ? (int) $data['max_length'] : null;
        $field->char_counter = (int) ($data['char_counter'] ?? 0);
        $field->position = (int) $data['position'];
        $field->active = (int) $data['active'];
        $field->preview_pos_x = ($data['preview_pos_x'] ?? null) !== null ? (string) $data['preview_pos_x'] : null;
        $field->preview_pos_y = ($data['preview_pos_y'] ?? null) !== null ? (string) $data['preview_pos_y'] : null;
        $field->preview_font = $data['preview_font'] ?? null;
        $field->preview_font_size = ($data['preview_font_size'] ?? null) !== null ? (string) $data['preview_font_size'] : null;
        $field->preview_max_width = ($data['preview_max_width'] ?? null) !== null ? (string) $data['preview_max_width'] : null;
        $field->preview_color = $data['preview_color'] ?? null;
        // id_background is intentionally left untouched here: it's no longer
        // exposed on the field form (removed in favor of combination rules,
        // see BackgroundRuleType), but the column/resolution logic stays as a
        // dormant safety net for any field that already had one set — the
        // constructor above already loaded it from the DB, so simply not
        // assigning it here preserves whatever value it had.
        $field->is_front_field = (int) ($data['is_front_field'] ?? 1);

        if ($id > 0) {
            $field->update();
        } else {
            $field->add();
        }

        $productIds = $scopeType === MzcProductFieldsField::SCOPE_PRODUCTS
            ? $this->decodeProductIds($data['products_json'] ?? '[]')
            : [];
        $this->syncProductAssociations((int) $field->id, $productIds);

        return (int) $field->id;
    }

    public function delete(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $field = new MzcProductFieldsField($id);
        if (!$field->id) {
            return false;
        }

        return (bool) $field->delete();
    }

    /**
     * @return array<int,int>
     */
    public function getProductIdsForField(int $idField): array
    {
        if ($idField <= 0) {
            return [];
        }

        $rows = Db::getInstance()->executeS(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'mzc_productfields_field_product`
             WHERE `id_mzc_productfields_field` = ' . (int) $idField
        );

        return $rows ? array_map(static fn ($row) => (int) $row['id_product'], $rows) : [];
    }

    private function syncProductAssociations(int $idField, array $productIds): void
    {
        if ($idField <= 0) {
            return;
        }

        Db::getInstance()->delete('mzc_productfields_field_product', '`id_mzc_productfields_field` = ' . (int) $idField);

        foreach (array_unique(array_map('intval', $productIds)) as $idProduct) {
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
     * @return array<int,int> ids of SCOPE_PRODUCTS fields that currently include this product —
     *                         backs the checkbox pre-check state on the product edit page's "quick override"
     */
    public function getFieldIdsForProduct(int $idProduct): array
    {
        if ($idProduct <= 0) {
            return [];
        }

        $rows = Db::getInstance()->executeS(
            'SELECT `id_mzc_productfields_field` FROM `' . _DB_PREFIX_ . 'mzc_productfields_field_product`
             WHERE `id_product` = ' . (int) $idProduct
        );

        return $rows ? array_map(static fn ($row) => (int) $row['id_mzc_productfields_field'], $rows) : [];
    }

    /**
     * Sets this product's membership across a given set of SCOPE_PRODUCTS
     * fields in one pass — used by the product edit page's "quick override"
     * checkboxes (see ProductFieldsFormModifier), which only ever presents
     * the full universe of active SCOPE_PRODUCTS fields, so an unchecked one
     * unambiguously means "remove this product from it".
     *
     * @param array<int,int> $checkedFieldIds field ids this product should belong to, out of $allFieldIds
     * @param array<int,int> $allFieldIds the full set of field ids that were offered as checkboxes
     */
    public function syncProductMembership(int $idProduct, array $checkedFieldIds, array $allFieldIds): void
    {
        if ($idProduct <= 0) {
            return;
        }

        $checked = array_flip(array_map('intval', $checkedFieldIds));

        foreach (array_map('intval', $allFieldIds) as $idField) {
            Db::getInstance()->delete(
                'mzc_productfields_field_product',
                '`id_mzc_productfields_field` = ' . (int) $idField . ' AND `id_product` = ' . (int) $idProduct
            );

            if (isset($checked[$idField])) {
                Db::getInstance()->insert('mzc_productfields_field_product', [
                    'id_mzc_productfields_field' => $idField,
                    'id_product' => $idProduct,
                ]);
            }
        }
    }

    /**
     * @return array<int,int>
     */
    private function decodeProductIds(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $decoded), static fn ($id) => $id > 0));
    }

    /**
     * @return array<int,array{id:int,name:string,image:string}> pre-filled entries for the product autocomplete widget
     */
    private function getProductEntriesForField(int $idField, int $idLang): array
    {
        return $this->getProductEntries($this->getProductIdsForField($idField), $idLang);
    }

    /**
     * Autocomplete-widget entries for a list of product ids, in the order given;
     * ids that no longer exist are dropped.
     *
     * @param array<int,int> $productIds
     *
     * @return array<int,array{id:int,name:string,image:string}>
     */
    public function getProductEntries(array $productIds, int $idLang): array
    {
        $entries = [];
        foreach ($productIds as $idProduct) {
            $product = new Product((int) $idProduct, false, $idLang);
            if (!Validate::isLoadedObject($product)) {
                continue;
            }
            $entries[] = [
                'id' => (int) $idProduct,
                'name' => $product->name,
                'image' => $this->getProductImageUrl((int) $idProduct),
            ];
        }

        return $entries;
    }

    private function getProductImageUrl(int $idProduct): string
    {
        $idImage = Product::getCover($idProduct);
        if (!$idImage || empty($idImage['id_image'])) {
            return '';
        }

        return Context::getContext()->link->getImageLink('default', (int) $idImage['id_image'], 'small_default');
    }

    /**
     * Search categories by name or numeric id — backs the category autocomplete
     * widget (core has no reusable equivalent of ProductSearchType for categories).
     *
     * @return array<int,array{id:int,name:string,image:string}>
     */
    public function searchCategories(string $query, int $idLang, int $limit = 20): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $conditions = ['cl.`name` LIKE "%' . pSQL($query) . '%"'];
        if (ctype_digit($query)) {
            $conditions[] = 'c.`id_category` = ' . (int) $query;
        }

        $sql = 'SELECT c.`id_category`, cl.`name`
                FROM `' . _DB_PREFIX_ . 'category` c
                INNER JOIN `' . _DB_PREFIX_ . 'category_lang` cl
                    ON cl.`id_category` = c.`id_category` AND cl.`id_lang` = ' . (int) $idLang . '
                WHERE (' . implode(' OR ', $conditions) . ') AND c.`active` = 1
                ORDER BY cl.`name` ASC
                LIMIT ' . (int) $limit;

        $rows = Db::getInstance()->executeS($sql) ?: [];

        return $this->formatCategoryEntries(array_map(static fn ($row) => (int) $row['id_category'], $rows));
    }

    /**
     * @param array<int,int> $categoryIds
     *
     * @return array<int,array{id:int,name:string,image:string}>
     */
    private function formatCategoryEntries(array $categoryIds): array
    {
        $idLang = (int) Context::getContext()->language->id;
        $entries = [];
        foreach ($categoryIds as $idCategory) {
            $category = new Category($idCategory, $idLang);
            if (!Validate::isLoadedObject($category)) {
                continue;
            }
            $entries[] = [
                'id' => $idCategory,
                'name' => $category->name,
                'image' => '',
            ];
        }

        return $entries;
    }
}
