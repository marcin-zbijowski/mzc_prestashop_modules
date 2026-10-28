<?php

declare(strict_types=1);

namespace Mzc\Brevo\Queue;

/**
 * Exponential back-off: 1, 5, 15 and 60 minutes, then 6 hours. The sixth failed
 * attempt makes the job dead — it stays in the table, visible in the back office,
 * until someone presses "retry".
 */
final class RetryPolicy
{
    public const DELAYS = [60, 300, 900, 3600, 21600];
    public const MAX_ATTEMPTS = 6;

    /** Seconds until the next attempt, or null when the job is dead after $attempts failures. */
    public static function delayAfter(int $attempts): ?int
    {
        if ($attempts >= self::MAX_ATTEMPTS) {
            return null;
        }

        return self::DELAYS[max(0, $attempts - 1)] ?? self::DELAYS[count(self::DELAYS) - 1];
    }
}
