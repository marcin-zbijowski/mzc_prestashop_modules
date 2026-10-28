<?php

declare(strict_types=1);

namespace Mzc\Brevo\Sync;

/**
 * Delivers a batch to an API that refuses a batch as a whole when one item in it is bad,
 * and finds the bad items.
 *
 * Brevo answers 400 for a whole /orders/status/batch when a single order has a line without a
 * product or an amount, and says only "at index 432". Sent as one request, a thousand good
 * orders are lost for one bad one. Here a refused batch is halved and each half retried, down to
 * single items: the good ones go through, the bad ones are returned by themselves, in a few dozen
 * requests (about 2 x log2 of the size per bad item).
 *
 * Only a refusal (4xx) is a verdict on the data and gets split. A request that failed (network,
 * 5xx after all the retries) says nothing about any one item, so it is not split.
 */
final class BatchBisector
{
    public const OK = 'ok';
    public const REJECTED = 'rejected';
    public const FAILED = 'failed';

    /**
     * @param list<mixed> $items
     * @param callable(list<mixed>, bool): string $send sends the items and returns OK, REJECTED
     *        or FAILED. The boolean is true for a single item: that refusal is final, so the
     *        sender may report it as an error rather than as a step towards finding one.
     *
     * @return array{accepted:list<mixed>,refused:list<mixed>,failed:list<mixed>}
     */
    public static function deliver(array $items, callable $send): array
    {
        $result = ['accepted' => [], 'refused' => [], 'failed' => []];

        if (!$items) {
            return $result;
        }

        $final = count($items) === 1;
        $outcome = $send($items, $final);

        if ($outcome === self::OK) {
            $result['accepted'] = $items;
        } elseif ($outcome === self::REJECTED && !$final) {
            $half = intdiv(count($items), 2);

            foreach ([array_slice($items, 0, $half), array_slice($items, $half)] as $part) {
                foreach (self::deliver($part, $send) as $key => $list) {
                    $result[$key] = array_merge($result[$key], $list);
                }
            }
        } elseif ($outcome === self::REJECTED) {
            $result['refused'] = $items;
        } else {
            $result['failed'] = $items;
        }

        return $result;
    }
}
