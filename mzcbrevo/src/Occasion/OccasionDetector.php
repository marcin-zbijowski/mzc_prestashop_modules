<?php

declare(strict_types=1);

namespace Mzc\Brevo\Occasion;

/**
 * Decides which occasion (christening, communion, ...) a product is for.
 *
 * The personalization text cannot tell: "pamiątka chrztu św." and "otulony Bożą
 * miłością" are both plausible on the same poster. So the occasion comes from the
 * product, in three steps, first hit wins:
 *
 *   1. a manual choice on the product page (the exceptions — including "none");
 *   2. the category → occasion mapping in the module's settings;
 *   3. keywords in the product name, after lowercasing and stripping Polish
 *      diacritics.
 *
 * No hit means no occasion — never "other". "Other" exists only as a manual choice.
 *
 * Pure: no database, no PrestaShop. OccasionExtractor feeds it.
 */
final class OccasionDetector
{
    public const CHRISTENING = 'chrzest';
    public const COMMUNION = 'komunia';
    public const CONFIRMATION = 'bierzmowanie';
    public const WEDDING = 'slub';
    public const OTHER = 'inne';

    /** Manual value meaning "this product is not for any occasion, whatever its name says". */
    public const NONE = 'none';

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_CATEGORY = 'category';
    public const SOURCE_NAME = 'name';

    /**
     * Values of the OCCASION_TYPE category attribute in Brevo. Brevo stores a
     * category attribute as the integer; the label is what its UI shows.
     */
    public const BREVO_VALUES = [
        self::CHRISTENING => 1,
        self::COMMUNION => 2,
        self::CONFIRMATION => 3,
        self::WEDDING => 4,
        self::OTHER => 5,
    ];

    public const LABELS = [
        self::CHRISTENING => 'chrzest',
        self::COMMUNION => 'komunia',
        self::CONFIRMATION => 'bierzmowanie',
        self::WEDDING => 'ślub',
        self::OTHER => 'inne',
    ];

    /** Name keywords (normalized) per occasion, checked in this order. */
    public const KEYWORDS = [
        self::CHRISTENING => ['chrz'],
        self::COMMUNION => ['komuni'],
        self::CONFIRMATION => ['bierzmowan'],
        self::WEDDING => ['slub', 'wesel', 'zaslubin'],
    ];

    /**
     * @param string|null $manual the product's manual choice, null or '' for automatic
     * @param int[] $categoryIds every category of the product, the default one first
     * @param array<int,string> $categoryMap id_category => occasion
     *
     * @return array{occasion:string,source:string}|null
     */
    public static function resolve(?string $manual, array $categoryIds, array $categoryMap, string $name): ?array
    {
        if ($manual !== null && $manual !== '') {
            if ($manual === self::NONE || !isset(self::BREVO_VALUES[$manual])) {
                return null;
            }

            return ['occasion' => $manual, 'source' => self::SOURCE_MANUAL];
        }

        foreach ($categoryIds as $idCategory) {
            $occasion = $categoryMap[(int) $idCategory] ?? '';

            if (isset(self::BREVO_VALUES[$occasion])) {
                return ['occasion' => $occasion, 'source' => self::SOURCE_CATEGORY];
            }
        }

        $occasion = self::fromName($name);

        return $occasion === null ? null : ['occasion' => $occasion, 'source' => self::SOURCE_NAME];
    }

    public static function fromName(string $name): ?string
    {
        $normalized = self::normalize($name);

        foreach (self::KEYWORDS as $occasion => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($normalized, $keyword) !== false) {
                    return $occasion;
                }
            }
        }

        return null;
    }

    public static function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');

        return strtr($text, [
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
            'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
        ]);
    }
}
