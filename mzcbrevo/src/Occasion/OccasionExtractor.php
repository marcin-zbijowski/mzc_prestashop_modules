<?php

declare(strict_types=1);

namespace Mzc\Brevo\Occasion;

use Configuration;
use Db;

/**
 * Reads what OccasionDetector needs from the shop: the manual choice stored by this
 * module's product tab, the product's categories and its name.
 *
 * It does not read the mzcproductfields values themselves — see OccasionDetector for
 * why the typed text is not a reliable signal. mzcproductfields is what makes these
 * products personalized; which occasion they serve is a property of the product.
 */
class OccasionExtractor
{
    public const TABLE = 'mzcbrevo_product_occasion';

    /** @var array<int,string> */
    private array $categoryMap;

    private int $idLang;

    /** @var array<int,array{occasion:string,source:string}|null> */
    private array $cache = [];

    /**
     * @param array<int,string> $categoryMap id_category => occasion, from Config::categoryOccasions()
     */
    public function __construct(array $categoryMap, ?int $idLang = null)
    {
        $this->categoryMap = $categoryMap;
        $this->idLang = $idLang ?? (int) Configuration::get('PS_LANG_DEFAULT');
    }

    /**
     * @return array{occasion:string,source:string}|null
     */
    public function forProduct(int $idProduct): ?array
    {
        if (!array_key_exists($idProduct, $this->cache)) {
            $this->forProducts([$idProduct]);
        }

        return $this->cache[$idProduct] ?? null;
    }

    /**
     * Resolves many products with three queries in total.
     *
     * @param int[] $idProducts
     *
     * @return array<int,array{occasion:string,source:string}|null>
     */
    public function forProducts(array $idProducts): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $idProducts))));
        $missing = array_values(array_diff($ids, array_keys($this->cache)));

        foreach (array_chunk($missing, 500) as $chunk) {
            $this->load($chunk);
        }

        return array_intersect_key($this->cache, array_flip($ids));
    }

    /**
     * @param int[] $ids
     */
    private function load(array $ids): void
    {
        $db = Db::getInstance();
        $in = implode(',', $ids);

        $products = $db->executeS(
            'SELECT p.`id_product`, p.`id_category_default`, pl.`name`, o.`occasion`
            FROM `' . _DB_PREFIX_ . 'product` p
            LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                ON pl.`id_product` = p.`id_product` AND pl.`id_lang` = ' . $this->idLang . '
                AND pl.`id_shop` = p.`id_shop_default`
            LEFT JOIN `' . _DB_PREFIX_ . self::TABLE . '` o ON o.`id_product` = p.`id_product`
            WHERE p.`id_product` IN (' . $in . ')'
        ) ?: [];

        $categories = [];

        if ($this->categoryMap) {
            $rows = $db->executeS(
                'SELECT `id_product`, `id_category` FROM `' . _DB_PREFIX_ . 'category_product`
                WHERE `id_product` IN (' . $in . ')
                ORDER BY `id_product`, `position`'
            ) ?: [];

            foreach ($rows as $row) {
                $categories[(int) $row['id_product']][] = (int) $row['id_category'];
            }
        }

        foreach ($ids as $id) {
            $this->cache[$id] = null;
        }

        foreach ($products as $product) {
            $id = (int) $product['id_product'];
            $default = (int) $product['id_category_default'];
            $productCategories = $categories[$id] ?? [];

            // The default category decides first: it is the one the merchant chose as "what this is".
            usort($productCategories, static fn (int $a, int $b): int => ($b === $default) <=> ($a === $default));

            $this->cache[$id] = OccasionDetector::resolve(
                $product['occasion'] !== null ? (string) $product['occasion'] : null,
                $productCategories ?: [$default],
                $this->categoryMap,
                (string) $product['name']
            );
        }
    }

    public static function getManual(int $idProduct): string
    {
        return (string) Db::getInstance()->getValue(
            'SELECT `occasion` FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE `id_product` = ' . $idProduct
        );
    }

    /** Saves the manual choice; '' goes back to automatic. */
    public static function setManual(int $idProduct, string $occasion): bool
    {
        $db = Db::getInstance();

        if ($occasion === '') {
            return (bool) $db->delete(self::TABLE, '`id_product` = ' . $idProduct);
        }

        if ($occasion !== OccasionDetector::NONE && !isset(OccasionDetector::BREVO_VALUES[$occasion])) {
            return false;
        }

        return (bool) $db->execute(
            'REPLACE INTO `' . _DB_PREFIX_ . self::TABLE . '` (`id_product`, `occasion`)
            VALUES (' . $idProduct . ', \'' . pSQL($occasion) . '\')'
        );
    }
}
