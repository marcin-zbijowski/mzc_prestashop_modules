<?php

declare(strict_types=1);

namespace Mzc\Brevo\Tests\Queue;

use Mzc\Brevo\Queue\Job;
use Mzc\Brevo\Queue\QueueRepository;
use Mzc\Brevo\Queue\RetryPolicy;

/**
 * QueueRepository without a database: the worker's view of the table, in an array.
 */
final class InMemoryQueue extends QueueRepository
{
    /** @var array<int,array{job:Job,status:string,attempts:int,delay:int,error:string}> */
    public array $rows = [];

    public bool $locked = false;

    public function __construct()
    {
    }

    public function add(Job $job): void
    {
        $this->rows[$job->id] = ['job' => $job, 'status' => Job::STATUS_PENDING, 'attempts' => $job->attempts, 'delay' => 0, 'error' => ''];
    }

    public function acquireLock(): bool
    {
        if ($this->locked) {
            return false;
        }

        return $this->locked = true;
    }

    public function releaseLock(): void
    {
        $this->locked = false;
    }

    public function resetStale(): void
    {
    }

    public function cleanup(): void
    {
    }

    public function claim(int $limit): array
    {
        $jobs = [];

        foreach ($this->rows as $id => $row) {
            if ($row['status'] === Job::STATUS_PENDING && count($jobs) < $limit) {
                $this->rows[$id]['status'] = Job::STATUS_PROCESSING;
                $jobs[] = $row['job'];
            }
        }

        return $jobs;
    }

    public function markDone(Job $job, ?array $request = null): void
    {
        $this->rows[$job->id]['status'] = Job::STATUS_DONE;
    }

    public function markFailed(Job $job, string $error, ?array $request = null): void
    {
        $this->rows[$job->id]['status'] = Job::STATUS_FAILED;
        $this->rows[$job->id]['attempts'] = $job->attempts + 1;
        $this->rows[$job->id]['error'] = $error;
    }

    public function markRetry(Job $job, string $error, bool $countsAsAttempt = true, ?array $request = null, int $minDelay = 0): void
    {
        $attempts = $job->attempts + ($countsAsAttempt ? 1 : 0);
        $delay = $countsAsAttempt ? RetryPolicy::delayAfter($attempts) : 0;

        $this->rows[$job->id]['attempts'] = $attempts;
        $this->rows[$job->id]['error'] = $error;
        $this->rows[$job->id]['status'] = $delay === null ? Job::STATUS_DEAD : Job::STATUS_PENDING;
        $this->rows[$job->id]['delay'] = max((int) $delay, $minDelay);
    }

    public function release(Job $job, int $delaySeconds = 0): void
    {
        $this->rows[$job->id]['status'] = Job::STATUS_PENDING;
        $this->rows[$job->id]['delay'] = $delaySeconds;
    }

    public function status(int $id): string
    {
        return $this->rows[$id]['status'];
    }
}
