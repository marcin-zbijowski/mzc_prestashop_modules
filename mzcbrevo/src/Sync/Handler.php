<?php

declare(strict_types=1);

namespace Mzc\Brevo\Sync;

use Mzc\Brevo\Queue\Job;
use Mzc\Brevo\Queue\Result;
use Mzc\Brevo\Queue\StopWorker;

/**
 * Sends one kind of job to Brevo. Gets every claimed job of its type for one shop
 * at once, so it can pack them into batch requests where Brevo has them.
 */
interface Handler
{
    /**
     * @param Job[] $jobs
     *
     * @return array<int,Result> job id => result; a job missing here is retried
     *
     * @throws StopWorker when the whole run has to stop (rate limit, outage, bad key)
     */
    public function handle(array $jobs): array;
}
