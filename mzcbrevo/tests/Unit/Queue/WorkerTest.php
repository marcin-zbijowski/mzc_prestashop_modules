<?php

declare(strict_types=1);

namespace Mzc\Brevo\Tests\Queue;

use Mzc\Brevo\Api\BrevoClient;
use Mzc\Brevo\Config;
use Mzc\Brevo\Queue\Job;
use Mzc\Brevo\Queue\RetryPolicy;
use Mzc\Brevo\Queue\Worker;
use Mzc\Brevo\Sync\Handler;
use Mzc\Brevo\Sync\PingHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class WorkerTest extends TestCase
{
    private InMemoryQueue $queue;
    private Config $config;

    /** @var string[] */
    private array $critical = [];

    protected function setUp(): void
    {
        \Configuration::$values = [];
        $this->queue = new InMemoryQueue();
        $this->config = new Config(1);
        $this->config->setApiKey('xkeysib-test');
        $this->critical = [];
    }

    /**
     * @param MockResponse[] $responses one per request, in order
     */
    private function worker(array $responses): Worker
    {
        $client = new BrevoClient('xkeysib-test', new MockHttpClient($responses));

        return new Worker(
            $this->queue,
            fn (int $idShop, string $type): ?Handler => $type === Job::TYPE_PING ? new PingHandler($client, $this->config) : null,
            fn (int $idShop): Config => $this->config,
            function (string $message): void {
                $this->critical[] = $message;
            }
        );
    }

    private function ping(int $id, int $attempts = 0): Job
    {
        $job = new Job($id, 1, Job::TYPE_PING, 0, [], $attempts);
        $this->queue->add($job);

        return $job;
    }

    public function testSuccessMarksDone(): void
    {
        $this->ping(1);
        $report = $this->worker([new MockResponse('{"email":"a@b.c"}', ['http_code' => 200])])->run();

        self::assertSame(Job::STATUS_DONE, $this->queue->status(1));
        self::assertSame(1, $report->done);
        self::assertFalse($this->queue->locked, 'the lock is released');
    }

    public function testClientErrorFailsWithoutRetry(): void
    {
        $this->ping(1);
        $this->worker([new MockResponse('{"code":"invalid_parameter","message":"bad"}', ['http_code' => 400])])->run();

        self::assertSame(Job::STATUS_FAILED, $this->queue->status(1));
        self::assertSame('HTTP 400 invalid_parameter: bad', $this->queue->rows[1]['error']);
    }

    public function testServerErrorRetriesAndPauses(): void
    {
        $this->ping(1);
        $report = $this->worker([new MockResponse('oops', ['http_code' => 503])])->run();

        self::assertSame(Job::STATUS_PENDING, $this->queue->status(1));
        self::assertSame(1, $this->queue->rows[1]['attempts']);
        self::assertSame(60, $this->queue->rows[1]['delay']);
        self::assertSame([1 => 60], $report->paused);
        self::assertGreaterThan(time(), $this->config->getInt(Config::PAUSED_UNTIL));
    }

    public function testRateLimitCostsNoAttemptAndHonoursRetryAfter(): void
    {
        $this->ping(1, 2);
        $this->worker([new MockResponse('', ['http_code' => 429, 'response_headers' => ['Retry-After: 120']])])->run();

        self::assertSame(Job::STATUS_PENDING, $this->queue->status(1));
        self::assertSame(2, $this->queue->rows[1]['attempts']);
        self::assertSame(120, $this->queue->rows[1]['delay']);
    }

    public function testRefusedKeyIsLoggedAsCritical(): void
    {
        $this->ping(1);
        $this->worker([new MockResponse('{"message":"Key not found","code":"unauthorized"}', ['http_code' => 401])])->run();

        self::assertSame(Job::STATUS_PENDING, $this->queue->status(1));
        self::assertCount(1, $this->critical);
        self::assertStringContainsString('Key not found', $this->critical[0]);
    }

    public function testSixthFailureBuriesTheJob(): void
    {
        $this->ping(1, RetryPolicy::MAX_ATTEMPTS - 1);
        $this->worker([new MockResponse('', ['http_code' => 500])])->run();

        self::assertSame(Job::STATUS_DEAD, $this->queue->status(1));
    }

    public function testNetworkErrorRetriesWithoutPausing(): void
    {
        $this->ping(1);
        $report = $this->worker([new MockResponse('', ['error' => 'Connection reset'])])->run();

        self::assertSame(Job::STATUS_PENDING, $this->queue->status(1));
        self::assertSame(60, $this->queue->rows[1]['delay']);
        self::assertSame([], $report->paused);
    }

    public function testPausedShopIsNotTouched(): void
    {
        $this->ping(1);
        $this->config->set(Config::PAUSED_UNTIL, time() + 30);
        $report = $this->worker([])->run();

        self::assertSame(Job::STATUS_PENDING, $this->queue->status(1));
        self::assertSame(0, $this->queue->rows[1]['attempts']);
        self::assertSame(0, $report->done);
    }

    public function testMissingKeyKeepsJobsWaiting(): void
    {
        $this->ping(1);
        $this->config->setApiKey('');
        $this->worker([])->run();

        self::assertSame(Job::STATUS_PENDING, $this->queue->status(1));
        self::assertSame(0, $this->queue->rows[1]['attempts']);
    }

    public function testUnknownTypeFails(): void
    {
        $this->queue->add(new Job(1, 1, 'nonsense', 0, []));
        $this->worker([])->run();

        self::assertSame(Job::STATUS_FAILED, $this->queue->status(1));
    }

    public function testAStopReleasesTheOtherTypesUntouched(): void
    {
        $this->ping(1);
        $this->queue->add(new Job(2, 1, Job::TYPE_PRODUCT, 5, []));

        $handlers = 0;
        $client = new BrevoClient('k', new MockHttpClient([new MockResponse('', ['http_code' => 429])]));
        $worker = new Worker(
            $this->queue,
            function (int $idShop, string $type) use ($client, &$handlers): ?Handler {
                ++$handlers;

                return new PingHandler($client, $this->config);
            },
            fn (): Config => $this->config
        );
        $worker->run();

        self::assertSame(1, $handlers, 'no handler is built after the stop');
        self::assertSame(Job::STATUS_PENDING, $this->queue->status(2));
        self::assertSame(0, $this->queue->rows[2]['attempts']);
    }

    public function testASecondWorkerDoesNothing(): void
    {
        $this->ping(1);
        $this->queue->locked = true;
        $report = $this->worker([])->run();

        self::assertNotNull($report->skipped);
        self::assertSame(Job::STATUS_PENDING, $this->queue->status(1));
    }

    public function testRetryPolicy(): void
    {
        self::assertSame(60, RetryPolicy::delayAfter(1));
        self::assertSame(300, RetryPolicy::delayAfter(2));
        self::assertSame(900, RetryPolicy::delayAfter(3));
        self::assertSame(3600, RetryPolicy::delayAfter(4));
        self::assertSame(21600, RetryPolicy::delayAfter(5));
        self::assertNull(RetryPolicy::delayAfter(6));
    }
}
