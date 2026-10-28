<?php

declare(strict_types=1);

namespace Mzc\Brevo\Queue;

use Mzc\Brevo\Config;
use Mzc\Brevo\Sync\Handler;

/**
 * Drains the queue: claims due jobs, hands them to their handler per shop and type,
 * and writes back what happened.
 *
 * Runs from cron every minute (`mzcbrevo:worker`). A named database lock keeps a slow
 * run and the next one from working at the same time. A rate limit, an outage or a
 * refused key stops the run (see StopWorker) and pauses the worker in Configuration,
 * so the next minute's cron does not hammer Brevo again.
 */
class Worker
{
    private QueueRepository $queue;

    /** @var callable(int $idShop, string $type): ?Handler */
    private $handlerFor;

    /** @var callable(int $idShop): Config */
    private $configFor;

    /** @var callable(string $message): void */
    private $logCritical;

    /**
     * @param callable(int, string): ?Handler $handlerFor null for a type with no handler
     * @param callable(int): Config $configFor
     * @param callable(string): void|null $logCritical
     */
    public function __construct(QueueRepository $queue, callable $handlerFor, callable $configFor, ?callable $logCritical = null)
    {
        $this->queue = $queue;
        $this->handlerFor = $handlerFor;
        $this->configFor = $configFor;
        $this->logCritical = $logCritical ?? static function (string $message): void {
        };
    }

    public function run(int $limit = 200): WorkerReport
    {
        $report = new WorkerReport();

        if (!$this->queue->acquireLock()) {
            $report->skipped = 'another worker is running';

            return $report;
        }

        try {
            $this->queue->resetStale();
            $this->process($this->queue->claim($limit), $report);
            $this->queue->cleanup();
        } finally {
            $this->queue->releaseLock();
        }

        return $report;
    }

    /**
     * @param Job[] $jobs
     */
    private function process(array $jobs, WorkerReport $report): void
    {
        $groups = [];

        foreach ($jobs as $job) {
            $groups[$job->idShop][$job->type][] = $job;
        }

        foreach ($groups as $idShop => $byType) {
            $config = ($this->configFor)((int) $idShop);
            $pausedFor = $config->getInt(Config::PAUSED_UNTIL) - time();

            if ($pausedFor <= 0 && $config->apiKey() === '') {
                // The key was removed after these were queued: they wait for a new one.
                $pausedFor = 300;
                $report->errors[] = 'No API key for shop ' . $idShop;
            }

            if ($pausedFor > 0) {
                foreach ($byType as $typeJobs) {
                    $this->releaseAll($typeJobs, $pausedFor);
                }
                $report->paused[(int) $idShop] = $pausedFor;

                continue;
            }

            $stopped = null;

            foreach ($byType as $type => $typeJobs) {
                if ($stopped !== null) {
                    // Brevo told us to stop: the rest of this shop's jobs wait, untouched.
                    $this->releaseAll($typeJobs, $stopped->pauseSeconds);

                    continue;
                }

                $handler = ($this->handlerFor)((int) $idShop, (string) $type);

                if ($handler === null) {
                    foreach ($typeJobs as $job) {
                        $this->queue->markFailed($job, 'No handler for job type "' . $type . '"');
                        ++$report->failed;
                    }

                    continue;
                }

                try {
                    $results = $handler->handle($typeJobs);
                } catch (StopWorker $e) {
                    $stopped = $e;
                    $config->set(Config::PAUSED_UNTIL, time() + $e->pauseSeconds);
                    $report->paused[(int) $idShop] = $e->pauseSeconds;
                    $report->errors[] = $e->getMessage();

                    if ($e->critical) {
                        ($this->logCritical)('Brevo refused the API key of shop ' . $idShop . ': ' . $e->getMessage());
                    }

                    foreach ($typeJobs as $job) {
                        $this->queue->markRetry($job, $e->getMessage(), $e->countsAsAttempt, null, $e->pauseSeconds);
                        ++$report->retried;
                    }

                    continue;
                } catch (\Throwable $e) {
                    // A bug or a broken record must not take down the jobs of every other type.
                    foreach ($typeJobs as $job) {
                        $this->queue->markRetry($job, get_class($e) . ': ' . $e->getMessage());
                        ++$report->retried;
                    }
                    $report->errors[] = $e->getMessage();

                    continue;
                }

                $this->apply($typeJobs, $results, $report);
            }
        }
    }

    /**
     * @param Job[] $jobs
     * @param array<int,Result> $results
     */
    private function apply(array $jobs, array $results, WorkerReport $report): void
    {
        foreach ($jobs as $job) {
            $result = $results[$job->id] ?? Result::retry('The handler returned no result for this job');

            switch ($result->kind) {
                case Result::DONE:
                    $this->queue->markDone($job, $result->request);
                    ++$report->done;
                    break;
                case Result::FAIL:
                    $this->queue->markFailed($job, $result->error, $result->request);
                    ++$report->failed;
                    break;
                default:
                    $this->queue->markRetry($job, $result->error, true, $result->request);
                    ++$report->retried;
            }
        }
    }

    /**
     * @param Job[] $jobs
     */
    private function releaseAll(array $jobs, int $delaySeconds): void
    {
        foreach ($jobs as $job) {
            $this->queue->release($job, $delaySeconds);
        }
    }
}
