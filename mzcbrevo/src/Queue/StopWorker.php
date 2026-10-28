<?php

declare(strict_types=1);

namespace Mzc\Brevo\Queue;

/**
 * Thrown by a handler when the rest of this run makes no sense: Brevo rate-limits
 * (429), is down (5xx) or refuses the key (401/403).
 *
 * The jobs of the batch in hand get a retry; everything else claimed in this run
 * goes back to pending untouched, and the worker stays paused for $pauseSeconds.
 */
final class StopWorker extends \RuntimeException
{
    public int $pauseSeconds;

    /** False for 429: being throttled says nothing about the job, so it costs no attempt. */
    public bool $countsAsAttempt;

    /** True for a refused key: worth a line in PrestaShop's log, the queue alone is not enough. */
    public bool $critical;

    public function __construct(string $message, int $pauseSeconds, bool $countsAsAttempt, bool $critical = false)
    {
        parent::__construct($message);
        $this->pauseSeconds = $pauseSeconds;
        $this->countsAsAttempt = $countsAsAttempt;
        $this->critical = $critical;
    }
}
