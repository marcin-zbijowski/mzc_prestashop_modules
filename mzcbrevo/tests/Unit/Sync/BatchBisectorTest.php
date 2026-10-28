<?php

declare(strict_types=1);

namespace Mzc\Brevo\Tests\Sync;

use Mzc\Brevo\Sync\BatchBisector;
use PHPUnit\Framework\TestCase;

final class BatchBisectorTest extends TestCase
{
    /** @var list<int> sizes of the batches the fake Brevo was asked about */
    private array $calls = [];

    /**
     * A Brevo that refuses (4xx) any batch containing one of $poisoned, as it does for one bad order.
     *
     * @param list<string> $poisoned
     */
    private function brevo(array $poisoned, string $refusal = BatchBisector::REJECTED): callable
    {
        $this->calls = [];

        return function (array $items, bool $final) use ($poisoned, $refusal): string {
            $this->calls[] = count($items);

            return array_intersect($items, $poisoned) ? $refusal : BatchBisector::OK;
        };
    }

    public function testACleanBatchIsOneRequest(): void
    {
        $result = BatchBisector::deliver(['a', 'b', 'c'], $this->brevo([]));

        self::assertSame(['a', 'b', 'c'], $result['accepted']);
        self::assertSame([], $result['refused']);
        self::assertSame([3], $this->calls);
    }

    public function testOneBadItemDoesNotTakeTheWholeBatchDown(): void
    {
        $items = array_map(static fn (int $i): string => 'o' . $i, range(1, 884));

        $result = BatchBisector::deliver($items, $this->brevo(['o433']));

        self::assertSame(['o433'], $result['refused']);
        self::assertCount(883, $result['accepted']);
        self::assertNotContains('o433', $result['accepted']);
        self::assertSame([], $result['failed']);
        // log2(884) levels, a sibling request at each: tens of requests, not hundreds.
        self::assertLessThan(30, count($this->calls));
    }

    public function testSeveralBadItemsAreAllFound(): void
    {
        $result = BatchBisector::deliver(['a', 'b', 'c', 'd', 'e', 'f'], $this->brevo(['b', 'f']));

        self::assertSame(['b', 'f'], $result['refused']);
        self::assertSame(['a', 'c', 'd', 'e'], $result['accepted']);
    }

    public function testAnItemIsNeverSentWithoutBeingMarkedFinalWhenItIsAlone(): void
    {
        $finals = [];
        BatchBisector::deliver(['a', 'b'], function (array $items, bool $final) use (&$finals): string {
            $finals[] = [count($items), $final];

            return count($items) > 1 ? BatchBisector::REJECTED : BatchBisector::OK;
        });

        // The two-item batch is not final (it may still be split); each single item is.
        self::assertSame([[2, false], [1, true], [1, true]], $finals);
    }

    public function testAGivenUpBatchIsNotSplit(): void
    {
        // The network or Brevo failed, not the data: halving it would only repeat the failure.
        $result = BatchBisector::deliver(['a', 'b', 'c', 'd'], $this->brevo(['a'], BatchBisector::FAILED));

        self::assertSame(['a', 'b', 'c', 'd'], $result['failed']);
        self::assertSame([], $result['accepted']);
        self::assertSame([4], $this->calls);
    }

    public function testNothingToSendIsNoRequest(): void
    {
        $result = BatchBisector::deliver([], $this->brevo([]));

        self::assertSame([], $result['accepted']);
        self::assertSame([], $this->calls);
    }
}
