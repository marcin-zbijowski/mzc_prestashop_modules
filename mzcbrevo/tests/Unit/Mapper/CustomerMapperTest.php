<?php

declare(strict_types=1);

namespace Mzc\Brevo\Tests\Mapper;

use Mzc\Brevo\Mapper\CustomerMapper;
use Mzc\Brevo\Occasion\OccasionDetector;
use PHPUnit\Framework\TestCase;

final class CustomerMapperTest extends TestCase
{
    private static function customer(array $overrides = []): array
    {
        return $overrides + [
            'id_customer' => 123,
            'email' => 'anna@example.com',
            'firstname' => 'Anna',
            'lastname' => 'Kowalska',
            'id_default_group' => 3,
            'lang_iso' => 'PL',
            'phone' => '601 234 567|PL',
            'consent' => true,
            'consent_date' => '2026-03-01',
        ];
    }

    private static function stats(array $overrides = []): array
    {
        return $overrides + [
            'orders_count' => 2,
            'total_spent' => 349.9,
            'first_order_date' => '2025-04-10',
            'last_order_date' => '2026-05-02',
            'last_category' => 'Pamiątki komunijne',
            'occasion' => OccasionDetector::COMMUNION,
            'occasion_order_date' => '2026-05-02',
        ];
    }

    public function testMapsEveryAttribute(): void
    {
        $attributes = CustomerMapper::attributes(self::customer(), self::stats(), 'checkout');

        self::assertSame([
            'FIRSTNAME' => 'Anna',
            'LASTNAME' => 'Kowalska',
            'PS_CUSTOMER_ID' => 123,
            'PS_GROUP' => 3,
            'PS_LANG' => 'pl',
            'ORDERS_COUNT' => 2,
            'TOTAL_SPENT' => 349.9,
            'MARKETING_CONSENT' => true,
            'SMS' => '+48601234567',
            'CONSENT_DATE' => '2026-03-01',
            'FIRST_ORDER_DATE' => '2025-04-10',
            'LAST_ORDER_DATE' => '2026-05-02',
            'LAST_CATEGORY' => 'Pamiątki komunijne',
            'OCCASION_ORDER_DATE' => '2026-05-02',
            'OCCASION_TYPE' => 2,
            'NEWSLETTER_SOURCE' => 'checkout',
        ], $attributes);
    }

    public function testNoPhoneAndNoConsentDateWithoutConsent(): void
    {
        $attributes = CustomerMapper::attributes(self::customer(['consent' => false]), self::stats());

        self::assertFalse($attributes['MARKETING_CONSENT']);
        self::assertArrayNotHasKey('SMS', $attributes);
        self::assertArrayNotHasKey('CONSENT_DATE', $attributes);
    }

    public function testUnknownValuesAreLeftOutNotErased(): void
    {
        $attributes = CustomerMapper::attributes(self::customer(['phone' => 'abc|PL']), [
            'orders_count' => 0,
            'total_spent' => 0.0,
            'first_order_date' => null,
            'last_order_date' => null,
            'last_category' => '',
            'occasion' => null,
            'occasion_order_date' => null,
        ]);

        foreach (['SMS', 'FIRST_ORDER_DATE', 'LAST_ORDER_DATE', 'LAST_CATEGORY', 'OCCASION_TYPE', 'OCCASION_ORDER_DATE', 'NEWSLETTER_SOURCE'] as $key) {
            self::assertArrayNotHasKey($key, $attributes, $key);
        }

        self::assertSame(0, $attributes['ORDERS_COUNT']);
    }

    public function testListsWithConsent(): void
    {
        self::assertSame(['listIds' => [7, 5], 'unlinkListIds' => []], CustomerMapper::lists(true, 5, 7));
    }

    public function testListsWithoutConsentUnlinkTheNewsletter(): void
    {
        self::assertSame(['listIds' => [7], 'unlinkListIds' => [5]], CustomerMapper::lists(false, 5, 7));
    }

    public function testListsWaitingForDoubleOptInNeitherJoinNorLeave(): void
    {
        self::assertSame(['listIds' => [7], 'unlinkListIds' => []], CustomerMapper::lists(true, 5, 7, false));
    }

    public function testUnconfiguredListsAreSkipped(): void
    {
        self::assertSame(['listIds' => [], 'unlinkListIds' => []], CustomerMapper::lists(false, 0, 0));
    }

    public function testUpdateBodyCarriesTheEmailAsAttribute(): void
    {
        $body = CustomerMapper::updateBody('new@example.com', ['FIRSTNAME' => 'Anna'], ['listIds' => [7], 'unlinkListIds' => [5]]);

        self::assertSame([
            'attributes' => ['EMAIL' => 'new@example.com', 'FIRSTNAME' => 'Anna'],
            'listIds' => [7],
            'unlinkListIds' => [5],
        ], $body);
    }

    public function testCreateBodyIsAnUpsertWithExtId(): void
    {
        $body = CustomerMapper::createBody('anna@example.com', 'ps_123', ['FIRSTNAME' => 'Anna'], ['listIds' => [7], 'unlinkListIds' => [5]]);

        self::assertSame([
            'email' => 'anna@example.com',
            'ext_id' => 'ps_123',
            'attributes' => ['FIRSTNAME' => 'Anna'],
            'updateEnabled' => true,
            'listIds' => [7],
        ], $body);
    }
}
