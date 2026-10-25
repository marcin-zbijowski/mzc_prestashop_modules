<?php

declare(strict_types=1);

namespace Mzc\Brevo\Mapper;

/**
 * Products and categories → Brevo eCommerce catalogue (/v3/products/batch,
 * /v3/categories/batch). Brevo's product blocks in e-mails (abandoned cart above
 * all) read name, URL, image and price from here.
 */
final class CatalogMapper
{
    /**
     * @param array<string,mixed> $product ShopData::product()
     * @param callable(int): string $brevoId
     */
    public static function product(array $product, callable $brevoId): array
    {
        $result = [
            'id' => $brevoId((int) $product['id_product']),
            'name' => (string) $product['name'],
            'url' => (string) $product['url'],
            'price' => round((float) $product['price'], 2),
            'categories' => array_values(array_map(
                static fn ($id): string => $brevoId((int) $id),
                (array) $product['categories']
            )),
            'stock' => (float) $product['stock'],
            'isDeleted' => false,
            'metaInfo' => [
                'active' => (int) $product['active'],
            ],
        ];

        if ((string) $product['image'] !== '') {
            $result['imageUrl'] = (string) $product['image'];
        }

        if ((string) $product['reference'] !== '') {
            $result['sku'] = (string) $product['reference'];
        }

        if ((string) ($product['brand'] ?? '') !== '') {
            $result['brand'] = (string) $product['brand'];
        }

        $description = trim(html_entity_decode(strip_tags((string) ($product['description_short'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($description !== '') {
            $result['description'] = mb_substr($description, 0, 1000);
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $category ShopData::category()
     * @param callable(int): string $brevoId
     */
    public static function category(array $category, callable $brevoId): array
    {
        return [
            'id' => $brevoId((int) $category['id_category']),
            'name' => (string) $category['name'],
            'url' => (string) $category['url'],
            'isDeleted' => false,
        ];
    }

    /** A product or category that no longer exists in the shop. */
    public static function deleted(string $brevoId, ?int $timestamp = null): array
    {
        return [
            'id' => $brevoId,
            'isDeleted' => true,
            'deletedAt' => gmdate('Y-m-d\TH:i:s.000\Z', $timestamp ?? time()),
        ];
    }
}
