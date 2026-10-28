<?php

declare(strict_types=1);

namespace Mzc\Brevo\Tests\Mapper;

use Mzc\Brevo\Mapper\CatalogMapper;
use Mzc\Brevo\Mapper\EventMapper;
use PHPUnit\Framework\TestCase;

final class EventMapperTest extends TestCase
{
    private static function line(array $overrides = []): array
    {
        return $overrides + [
            'id_product' => 12,
            'id_product_attribute' => 44,
            'name' => 'Plakat na chrzest',
            'attributes' => 'Rozmiar : A3',
            'quantity' => 2,
            'price' => 59.9,
            'url' => 'https://artedeum.pl/12-plakat.html',
            'image' => 'https://artedeum.pl/34-large_default/plakat.jpg',
            'personalization' => 'Imię: Zosia; Data: 12.05.2026',
        ];
    }

    public function testCartEvent(): void
    {
        $event = EventMapper::cartEvent(EventMapper::CART_ABANDONED, [
            'id_cart' => 900,
            'email' => 'anna@example.com',
            'total' => 119.8,
            'currency_iso' => 'PLN',
            'lines' => [self::line()],
        ], 'https://artedeum.pl/module/mzcbrevo/recover?id_cart=900&t=abc', 1782900000);

        self::assertSame([
            'event_name' => 'cart_abandoned',
            'event_date' => '2026-07-01T10:00:00Z',
            'identifiers' => ['email_id' => 'anna@example.com'],
            'event_properties' => [
                'cart_id' => 900,
                'value' => 119.8,
                'currency' => 'PLN',
                'items_count' => 2,
                'items' => [[
                    'product_id' => 12,
                    'variant_id' => 44,
                    'name' => 'Plakat na chrzest',
                    'quantity' => 2,
                    'price' => 59.9,
                    'url' => 'https://artedeum.pl/12-plakat.html',
                    'image' => 'https://artedeum.pl/34-large_default/plakat.jpg',
                    'personalization' => 'Imię: Zosia; Data: 12.05.2026',
                    'variant' => 'Rozmiar : A3',
                ]],
                'recover_url' => 'https://artedeum.pl/module/mzcbrevo/recover?id_cart=900&t=abc',
            ],
        ], $event);
    }

    public function testOrderEvent(): void
    {
        $event = EventMapper::orderEvent(
            EventMapper::ORDER_STATUS_CHANGED,
            OrderMapperTest::order(),
            [self::line(['attributes' => ''])],
            '501',
            'cancelled',
            'Anulowane',
            1782900000
        );

        self::assertSame('order_status_changed', $event['event_name']);
        self::assertSame('anna@example.com', $event['identifiers']['email_id']);
        self::assertSame('501', $event['event_properties']['order_id']);
        self::assertSame('cancelled', $event['event_properties']['status']);
        self::assertSame('Anulowane', $event['event_properties']['state_name']);
        self::assertSame(189.0, $event['event_properties']['value']);
        self::assertSame('LATO10', $event['event_properties']['coupons']);
        self::assertArrayNotHasKey('variant', $event['event_properties']['items'][0]);
    }

    public function testLongTextsAndManyLinesAreCut(): void
    {
        $lines = array_fill(0, 40, self::line(['personalization' => str_repeat('ż', 800)]));
        $items = EventMapper::items($lines);

        self::assertCount(EventMapper::MAX_ITEMS, $items);
        self::assertSame(EventMapper::MAX_TEXT, mb_strlen($items[0]['personalization']));
        self::assertStringEndsWith('…', $items[0]['personalization']);
    }

    public function testCatalogueProduct(): void
    {
        $product = CatalogMapper::product([
            'id_product' => 12,
            'name' => 'Plakat na chrzest',
            'url' => 'https://artedeum.pl/12-plakat.html',
            'image' => 'https://artedeum.pl/34-large_default/plakat.jpg',
            'price' => 59.9,
            'categories' => [2, 10],
            'stock' => 5,
            'active' => 1,
            'reference' => 'PL-12',
            'brand' => '',
            'description_short' => '<p>Piękny&nbsp;plakat</p>',
        ], static fn (int $id): string => (string) $id);

        self::assertSame('12', $product['id']);
        self::assertSame(['2', '10'], $product['categories']);
        self::assertSame('PL-12', $product['sku']);
        self::assertSame("Piękny\u{a0}plakat", $product['description']);
        self::assertArrayNotHasKey('brand', $product);
        self::assertFalse($product['isDeleted']);
    }

    public function testDeletedItem(): void
    {
        self::assertSame(
            ['id' => '12', 'isDeleted' => true, 'deletedAt' => '2026-07-01T10:00:00.000Z'],
            CatalogMapper::deleted('12', 1782900000)
        );
    }
}
