<?php

declare(strict_types=1);

namespace Mzc\Brevo\Sync;

use Mzc\Brevo\Api\BrevoClient;
use Mzc\Brevo\Api\Response;
use Mzc\Brevo\Api\TransportException;
use Mzc\Brevo\Config;
use Mzc\Brevo\Queue\Result;
use Mzc\Brevo\Queue\StopWorker;

abstract class AbstractHandler implements Handler
{
    /** Pause after a 5xx, or after a 429 without Retry-After. */
    public const DEFAULT_PAUSE = 60;

    /** Pause after a refused key: nothing will work until someone fixes it. */
    public const AUTH_PAUSE = 300;

    protected BrevoClient $client;
    protected Config $config;

    public function __construct(BrevoClient $client, Config $config)
    {
        $this->client = $client;
        $this->config = $config;
    }

    /**
     * Sends a request and turns the answer into a Result, or stops the worker.
     *
     *   2xx           done
     *   401, 403      stop + critical log: the key is wrong or lacks permission
     *   429           stop without costing an attempt, for Retry-After or 60 s
     *   5xx           stop, for Retry-After or 60 s; the batch gets a retry
     *   other 4xx     failed for good: the data is wrong, retrying changes nothing
     *
     * @param callable(): Response $send
     */
    protected function call(callable $send, ?array $request = null): Result
    {
        try {
            $response = $send();
        } catch (TransportException $e) {
            return Result::retry($e->getMessage(), $request);
        }

        return $this->interpret($response, $request);
    }

    /**
     * A request whose non-2xx answer the caller wants to look at itself; only the
     * statuses that stop the worker are handled here.
     *
     * @param callable(): Response $request
     *
     * @return Response|Result a Result (retry) when Brevo could not be reached
     */
    protected function raw(callable $request)
    {
        try {
            $response = $request();
        } catch (TransportException $e) {
            return Result::retry($e->getMessage());
        }

        self::stopIfGlobal($response);

        return $response;
    }

    protected function interpret(Response $response, ?array $request = null): Result
    {
        if ($response->isSuccess()) {
            return Result::done($request);
        }

        self::stopIfGlobal($response);

        return Result::fail($response->errorMessage(), $request);
    }

    /**
     * Sends items in batches, and when Brevo refuses a whole batch with a 4xx,
     * sends its items one by one: one malformed product must not fail the hundred
     * good ones packed with it.
     *
     * @param array<int,array> $items job id => the item for the batch
     * @param callable(list<array>): Response $send
     *
     * @return array<int,Result> job id => result
     */
    protected function sendInBatches(array $items, int $size, callable $send): array
    {
        $results = [];

        foreach (array_chunk($items, max(1, $size), true) as $chunk) {
            $result = $this->call(static fn (): Response => $send(array_values($chunk)));

            if ($result->kind === Result::FAIL && count($chunk) > 1) {
                foreach ($chunk as $jobId => $item) {
                    $results[$jobId] = $this->call(static fn (): Response => $send([$item]), $item);
                }

                continue;
            }

            foreach ($chunk as $jobId => $item) {
                $results[$jobId] = $result->withRequest($item);
            }
        }

        return $results;
    }

    /**
     * @throws StopWorker for the statuses that concern the whole run, not one job
     */
    public static function stopIfGlobal(Response $response): void
    {
        if ($response->status === 401 || $response->status === 403) {
            throw new StopWorker($response->errorMessage(), self::AUTH_PAUSE, true, true);
        }

        if ($response->status === 429) {
            throw new StopWorker($response->errorMessage(), $response->retryAfter() ?? self::DEFAULT_PAUSE, false);
        }

        if ($response->status >= 500) {
            throw new StopWorker($response->errorMessage(), $response->retryAfter() ?? self::DEFAULT_PAUSE, true);
        }
    }
}
