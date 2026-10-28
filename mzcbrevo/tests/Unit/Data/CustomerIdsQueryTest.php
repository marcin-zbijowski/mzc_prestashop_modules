<?php

declare(strict_types=1);

namespace Mzc\Brevo\Tests\Data;

use Mzc\Brevo\Data\ShopData;
use PHPUnit\Framework\TestCase;

/**
 * Which customers the historical import selects. The SQL is checked as text: ShopData needs a
 * database for everything else, and the rule that matters here — what `--since` lets in — is
 * entirely in the WHERE clause.
 */
final class CustomerIdsQueryTest extends TestCase
{
    public function testWithoutSinceEveryCustomerIsSelected(): void
    {
        $sql = ShopData::customerIdsQuery(1, null, 0, 1000);

        self::assertStringNotContainsString('date_upd', $sql);
        self::assertStringNotContainsString('newsletter', $sql);
        self::assertStringContainsString('`deleted` = 0', $sql);
        self::assertStringContainsString('LIMIT 1000', $sql);
    }

    public function testSinceKeepsEveryoneWhoConsentedEvenIfTheirAccountIsOld(): void
    {
        // A customer who ticked the newsletter box and whose account nobody touched for two years
        // is still somebody the newsletter has to reach.
        $sql = ShopData::customerIdsQuery(1, '2025-10-28 00:00:00', 0, 1000);

        self::assertMatchesRegularExpression('/`newsletter` = 1/', $sql);
    }

    public function testSinceAlsoKeepsCustomersWithAnOrderInThePeriod(): void
    {
        // date_upd says when the account row was last saved, not when the customer last bought.
        $sql = ShopData::customerIdsQuery(1, '2025-10-28 00:00:00', 0, 1000);

        self::assertStringContainsString('`id_customer` IN (SELECT `id_customer` FROM `ps_orders` WHERE `date_add` >= \'2025-10-28 00:00:00\'', $sql);
        self::assertStringContainsString('`date_upd` >= \'2025-10-28 00:00:00\'', $sql);
    }

    public function testTheSinceConditionsAreOneOrGroup(): void
    {
        // Without the parentheses the OR would swallow the shop, deleted and anonymous filters.
        $sql = ShopData::customerIdsQuery(1, '2025-10-28 00:00:00', 0, 1000);

        self::assertMatchesRegularExpression('/AND \(`date_upd` >= .+ OR `newsletter` = 1 OR `id_customer` IN \(.+\)\)/s', $sql);
        self::assertStringContainsString('`deleted` = 0', $sql);
        self::assertStringContainsString('NOT LIKE \'%@psgdpr.com\'', $sql);
    }

    public function testPagingAndOrdering(): void
    {
        $sql = ShopData::customerIdsQuery(2, null, 500, 100);

        self::assertStringContainsString('`id_shop` = 2', $sql);
        self::assertStringContainsString('`id_customer` > 500', $sql);
        self::assertStringContainsString('ORDER BY `id_customer` ASC LIMIT 100', $sql);
    }

    public function testTheDateIsEscaped(): void
    {
        $sql = ShopData::customerIdsQuery(1, "2025-10-28'; DROP TABLE x; --", 0, 10);

        // The quote is there, but escaped: a backslash in front of every one that came from the date.
        self::assertStringContainsString("\\'; DROP", $sql);
        self::assertDoesNotMatchRegularExpression("/(?<!\\\\)'; DROP/", $sql);
    }
}
