<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Unified mapper: product/cart/order → GA4 items array.
 * Single source of truth for all dataLayer item data.
 * All prices are gross (tax-included).
 */
class MzcItemMapper
{
    /** @var Context */
    private $context;

    /** @var array<int, string[]> per-request category path cache keyed by id_product */
    private $categoryCache = [];

    public function __construct($context)
    {
        $this->context = $context;
    }

    /**
     * Maps a product to a GA4 item array.
     *
     * Accepts:
     *   - Product object (from actionCartUpdateQuantityBefore)
     *   - ProductLazyArray from PS8 Smarty context (product page, listing)
     *   - Raw cart product row from Cart::getProducts()
     *
     * Price resolution order (gross / tax-included):
     *   1. price_amount  — PS8 product page presenter float
     *   2. price_wt      — Cart::getProducts() row, gross unit price
     *   3. Product::getPrice(true) — live gross price (fallback for Product objects)
     *
     * @param Product|array $product
     * @param int $qty
     * @return array GA4 item
     */
    public function mapProductToItem($product, int $qty = 1): array
    {
        if ($product instanceof Product) {
            return $this->mapProductObjectToItem($product, $qty);
        }

        $id         = isset($product['id_product']) ? (int) $product['id_product'] : (int) ($product['id'] ?? 0);
        $idAttr     = (int) ($product['id_product_attribute'] ?? 0);
        $langPrefix = strtoupper($this->context->language->iso_code);
        $itemId     = $idAttr ? "{$langPrefix}{$id}v{$idAttr}" : "{$langPrefix}{$id}";
        $name   = (string) ($product['name'] ?? '');

        if (isset($product['price_amount'])) {
            $price = (float) $product['price_amount'];
        } elseif (isset($product['price_wt'])) {
            $price = (float) $product['price_wt'];
        } elseif ($id > 0) {
            $price = (float) Product::getPriceStatic($id, true, $idAttr ?: null);
        } else {
            $price = 0.0;
        }

        $item = [
            'item_id'   => $itemId,
            'item_name' => $name,
            'price'     => round($price, 2),
            'quantity'  => $qty,
        ];

        if (!empty($product['manufacturer_name'])) {
            $item['item_brand'] = (string) $product['manufacturer_name'];
        }

        $variant = $product['attributes_small'] ?? $product['combination_name'] ?? '';
        if ($variant !== '') {
            $item['item_variant'] = strip_tags((string) $variant);
        }

        $categories = $this->getCategoriesForProduct($id);
        $this->assignCategories($item, $categories);

        return $item;
    }

    /**
     * Maps all products in a Cart to GA4 items.
     * Uses Cart::getProducts() which returns price_wt (gross unit price).
     */
    public function mapCartToItems($cart): array
    {
        $products = $cart->getProducts();
        $items    = [];

        foreach ($products as $row) {
            $qty     = (int) ($row['cart_quantity'] ?? 1);
            $items[] = $this->mapProductToItem($row, $qty);
        }

        return $items;
    }

    /**
     * Maps Order products to GA4 items.
     * Uses unit_price_tax_incl for gross per-unit price.
     */
    public function mapOrderToItems($order): array
    {
        $products = $order->getProductsDetail();
        $items    = [];

        foreach ($products as $row) {
            $qty = (int) ($row['product_quantity'] ?? 1);
            $normalized = [
                'id_product'           => $row['product_id'],
                'id_product_attribute' => $row['product_attribute_id'] ?? 0,
                'name'                 => $row['product_name'],
                'price_wt'             => (float) $row['unit_price_tax_incl'],
                'manufacturer_name'    => $row['manufacturer_name'] ?? '',
            ];
            $items[] = $this->mapProductToItem($normalized, $qty);
        }

        return $items;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Maps a Product object to a GA4 item. Used from actionCartUpdateQuantityBefore
     * where $params['product'] is a Product instance.
     */
    private function mapProductObjectToItem(Product $product, int $qty): array
    {
        $id         = (int) $product->id;
        $langPrefix = strtoupper($this->context->language->iso_code);
        $itemId     = "{$langPrefix}{$id}"; // no attr — caller overrides item_id if needed

        $langId = (int) $this->context->language->id;
        $name   = is_array($product->name)
            ? ($product->name[$langId] ?? reset($product->name))
            : (string) $product->name;

        $price = (float) Product::getPriceStatic($id, true);

        $item = [
            'item_id'   => $itemId,
            'item_name' => (string) $name,
            'price'     => round($price, 2),
            'quantity'  => $qty,
        ];

        if ((int) $product->id_manufacturer > 0) {
            $brand = Manufacturer::getNameById((int) $product->id_manufacturer);
            if ($brand) {
                $item['item_brand'] = $brand;
            }
        }

        $categories = $this->getCategoriesForProduct($id);
        $this->assignCategories($item, $categories);

        return $item;
    }

    /**
     * Returns an ordered array of category names (root→leaf) for the product's
     * default category, stopping at home/root and capping at 5 levels.
     * Results are cached per product ID for the duration of the request.
     */
    private function getCategoriesForProduct(int $idProduct): array
    {
        if ($idProduct <= 0) {
            return [];
        }

        if (isset($this->categoryCache[$idProduct])) {
            return $this->categoryCache[$idProduct];
        }

        $langId  = (int) $this->context->language->id;
        $homeId  = (int) Configuration::get('PS_HOME_CATEGORY');
        $rootId  = (int) Configuration::get('PS_ROOT_CATEGORY');

        // Get default category ID for product
        $idCategory = (int) Db::getInstance()->getValue(
            'SELECT `id_category_default` FROM `' . _DB_PREFIX_ . 'product`
             WHERE `id_product` = ' . $idProduct
        );

        if ($idCategory <= 0) {
            $this->categoryCache[$idProduct] = [];
            return [];
        }

        $categories = [];
        $current    = $idCategory;
        $limit      = 5;

        while ($current > 0 && $current !== $homeId && $current !== $rootId && $limit-- > 0) {
            $row = Db::getInstance()->getRow(
                'SELECT cl.`name`, c.`id_parent`
                 FROM `' . _DB_PREFIX_ . 'category` c
                 LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl
                     ON c.`id_category` = cl.`id_category`
                     AND cl.`id_lang` = ' . $langId . '
                     AND cl.`id_shop` = ' . (int) $this->context->shop->id . '
                 WHERE c.`id_category` = ' . (int) $current
            );

            if (empty($row)) {
                break;
            }

            array_unshift($categories, $row['name']);
            $current = (int) $row['id_parent'];
        }

        $this->categoryCache[$idProduct] = $categories;
        return $categories;
    }

    /**
     * Assigns up to 5 category levels to a GA4 item array in-place.
     * GA4 spec: item_category, item_category2 … item_category5
     */
    private function assignCategories(array &$item, array $categories): void
    {
        foreach (array_values($categories) as $idx => $name) {
            if ($idx === 0) {
                $item['item_category'] = $name;
            } else {
                $item['item_category' . ($idx + 1)] = $name;
            }

            if ($idx >= 4) {
                break;
            }
        }
    }
}
