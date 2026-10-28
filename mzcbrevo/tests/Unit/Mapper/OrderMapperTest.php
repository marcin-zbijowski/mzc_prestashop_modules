<?php

declare(strict_types=1);

namespace Mzc\Brevo\Tests\Mapper;

use Mzc\Brevo\Mapper\OrderMapper;
use PHPUnit\Framework\TestCase;

final class OrderMapperTest extends TestCase
{
    public static function order(array $overrides = []): array
    {
        return $overrides + [
            'id_order' => 501,
            'id_cart' => 900,
            'reference' => 'XKBKNABJK',
            'current_state' => 2,
            'payment' => 'PayU BLIK',
            'total_paid_tax_incl' => '189.000000',
            'total_shipping_tax_incl' => '14.990000',
            'total_discounts_tax_incl' => '10.000000',
            'date_add' => '2026-07-01 12:30:00',
            'date_upd' => '2026-07-01 12:31:05',
            'currency_iso' => 'PLN',
            'email' => 'anna@example.com',
            'coupons' => ['LATO10'],
            'lines' => [
                ['product_id' => 12, 'product_attribute_id' => 0, 'product_quantity' => 1, 'unit_price_tax_incl' => '99.000000'],
                ['product_id' => 15, 'product_attribute_id' => 44, 'product_quantity' => 2, 'unit_price_tax_incl' => '42.505000'],
            ],
            'billing' => [
                'address1' => 'ul. Długa 1', 'address2' => '', 'city' => 'Kraków', 'postcode' => '30-001',
                'country_iso' => 'PL', 'country_name' => 'Polska', 'state_name' => null, 'phone' => '', 'phone_mobile' => '601234567',
            ],
        ];
    }

    public static function id(int $id): string
    {
        return 'ad' . $id;
    }

    public function testMapsAnOrder(): void
    {
        $mapped = OrderMapper::toOrder(self::order(), [2 => 'completed'], [self::class, 'id']);

        self::assertSame([
            'id' => 'ad501',
            'createdAt' => '2026-07-01T10:30:00Z',
            'updatedAt' => '2026-07-01T10:31:05Z',
            'status' => 'completed',
            'amount' => 189.0,
            'products' => [
                ['productId' => 'ad12', 'quantity' => 1, 'price' => 99.0],
                ['productId' => 'ad15', 'quantity' => 2, 'price' => 42.51, 'variantId' => 'ad44'],
            ],
            'metaInfo' => ['reference' => 'XKBKNABJK', 'currency' => 'PLN', 'shipping' => 14.99, 'discounts' => 10.0],
            'identifiers' => ['email_id' => 'anna@example.com'],
            'coupons' => ['LATO10'],
            'billing' => [
                'address' => 'ul. Długa 1',
                'city' => 'Kraków',
                'postCode' => '30-001',
                'country' => 'Polska',
                'countryCode' => 'PL',
                'phone' => '601234567',
                'paymentMethod' => 'PayU BLIK',
            ],
        ], $mapped);
    }

    public function testAStateMappedToNothingIsNotSent(): void
    {
        self::assertNull(OrderMapper::toOrder(self::order(), [2 => ''], [self::class, 'id']));
        self::assertNull(OrderMapper::toOrder(self::order(), [], [self::class, 'id']));
    }

    public function testAnAnonymizedOrderIsRevenueWithoutAPerson(): void
    {
        $mapped = OrderMapper::toOrder(self::order(['email' => 'anonymous@psgdpr.com']), [2 => 'completed'], [self::class, 'id']);

        self::assertArrayNotHasKey('identifiers', $mapped);
        self::assertSame(189.0, $mapped['amount']);
    }

    public function testUtcHandlesDaylightSavingTime(): void
    {
        self::assertSame('2026-01-15T11:00:00Z', OrderMapper::utc('2026-01-15 12:00:00', 'Europe/Warsaw'));
        self::assertSame('2026-07-15T10:00:00Z', OrderMapper::utc('2026-07-15 12:00:00', 'Europe/Warsaw'));
    }

    public function testLinesBrevoWouldRefuseAreLeftOut(): void
    {
        // Brevo answers 400 "order's ProductId and quantity is mandatory" for a line without a
        // product or with no quantity, and refuses the whole batch it was in.
        $order = self::order(['lines' => [
            ['product_id' => 12, 'product_attribute_id' => 0, 'product_quantity' => 1, 'unit_price_tax_incl' => '99.000000'],
            ['product_id' => 0, 'product_attribute_id' => 0, 'product_quantity' => 1, 'unit_price_tax_incl' => '5.000000'],
            ['product_id' => 15, 'product_attribute_id' => 0, 'product_quantity' => 0, 'unit_price_tax_incl' => '5.000000'],
        ]]);

        $mapped = OrderMapper::toOrder($order, [2 => 'completed'], [self::class, 'id']);

        self::assertSame([['productId' => 'ad12', 'quantity' => 1, 'price' => 99.0]], $mapped['products']);
    }

    public function testAnOrderWithNothingSendableIsNotMapped(): void
    {
        $order = self::order(['lines' => [
            ['product_id' => 15, 'product_attribute_id' => 0, 'product_quantity' => 0, 'unit_price_tax_incl' => '5.000000'],
        ]]);

        self::assertNull(OrderMapper::toOrder($order, [2 => 'completed'], [self::class, 'id']));
        self::assertNull(OrderMapper::toOrder(self::order(['lines' => []]), [2 => 'completed'], [self::class, 'id']));
    }

    public function testSkipReasonTellsAnUnmappedStateFromAnEmptyOrder(): void
    {
        $map = [2 => 'completed'];

        self::assertNull(OrderMapper::skipReason(self::order(), $map));
        self::assertSame(OrderMapper::SKIP_STATE, OrderMapper::skipReason(self::order(['current_state' => 99]), $map));
        self::assertSame(OrderMapper::SKIP_LINES, OrderMapper::skipReason(self::order(['lines' => []]), $map));
        // An unmapped state wins: that is the answer the operator controls.
        self::assertSame(OrderMapper::SKIP_STATE, OrderMapper::skipReason(self::order(['current_state' => 99, 'lines' => []]), $map));
    }
}
