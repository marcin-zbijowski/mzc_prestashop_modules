<?php

declare(strict_types=1);

namespace Mzc\Brevo\Tests\Sync;

use Mzc\Brevo\Api\BrevoClient;
use Mzc\Brevo\Config;
use Mzc\Brevo\Data\ShopData;
use Mzc\Brevo\Mapper\EventMapper;
use Mzc\Brevo\Queue\Job;
use Mzc\Brevo\Queue\Result;
use Mzc\Brevo\Sync\CatalogHandler;
use Mzc\Brevo\Sync\DoiHandler;
use Mzc\Brevo\Sync\EventHandler;
use Mzc\Brevo\Sync\OrderHandler;
use Mzc\Brevo\Tests\Mapper\OrderMapperTest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * ShopData without a database: returns fixtures.
 */
final class FixtureShopData extends ShopData
{
    /** @var array<int,array> */
    public array $orders = [];

    /** @var array<int,array> */
    public array $products = [];

    public function __construct()
    {
    }

    public function order(int $idOrder): ?array
    {
        return $this->orders[$idOrder] ?? null;
    }

    public function orderEventLines(array $order): array
    {
        return [];
    }

    public function stateName(int $idOrderState): string
    {
        return 'State ' . $idOrderState;
    }

    public function product(int $idProduct): ?array
    {
        return $this->products[$idProduct] ?? null;
    }
}

final class HandlersTest extends TestCase
{
    /** @var array<int,array{method:string,url:string,body:mixed}> */
    private array $requests = [];

    private Config $config;

    protected function setUp(): void
    {
        \Configuration::$values = [];
        $this->config = new Config(1);
        $this->config->set(Config::STATUS_MAP, json_encode([2 => 'completed', 6 => 'cancelled', 10 => '']));
        $this->requests = [];
    }

    /**
     * @param MockResponse[] $responses
     */
    private function client(array $responses): BrevoClient
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'body' => json_decode($options['body'] ?? 'null', true)];

            return array_shift($responses);
        });

        return new BrevoClient('k', $http);
    }

    private static function product(int $id, string $name): array
    {
        return [
            'id_product' => $id, 'name' => $name, 'url' => 'https://x/' . $id, 'image' => '', 'price' => 10, 'categories' => [],
            'stock' => 1, 'active' => 1, 'reference' => '', 'brand' => '', 'description_short' => '',
        ];
    }

    public function testOrdersGoAsOneLiveBatchAndUnmappedStatesAreSkipped(): void
    {
        $data = new FixtureShopData();
        $data->orders[501] = OrderMapperTest::order();
        $data->orders[502] = OrderMapperTest::order(['id_order' => 502, 'current_state' => 10]);
        $data->orders[503] = OrderMapperTest::order(['id_order' => 503, 'current_state' => 6]);

        $handler = new OrderHandler($this->client([new MockResponse('{"batch_id": 7, "count": 2}', ['http_code' => 202])]), $this->config, $data);
        $results = $handler->handle([
            new Job(1, 1, Job::TYPE_ORDER, 501, []),
            new Job(2, 1, Job::TYPE_ORDER, 502, []),
            new Job(3, 1, Job::TYPE_ORDER, 503, []),
            new Job(4, 1, Job::TYPE_ORDER, 999, []),
        ]);

        self::assertCount(1, $this->requests);
        self::assertSame('https://api.brevo.com/v3/orders/status/batch', $this->requests[0]['url']);
        self::assertFalse($this->requests[0]['body']['historical']);
        self::assertSame(['501', '503'], array_column($this->requests[0]['body']['orders'], 'id'));
        self::assertSame(['completed', 'cancelled'], array_column($this->requests[0]['body']['orders'], 'status'));

        foreach ([1, 2, 3, 4] as $id) {
            self::assertSame(Result::DONE, $results[$id]->kind, 'job ' . $id);
        }
    }

    public function testARefusedBatchIsRetriedItemByItem(): void
    {
        $data = new FixtureShopData();
        $data->products[1] = self::product(1, 'Good');
        $data->products[2] = self::product(2, 'Bad');
        $data->products[3] = self::product(3, 'Good too');

        $handler = new CatalogHandler($this->client([
            new MockResponse('{"code":"invalid_parameter","message":"price"}', ['http_code' => 400]),
            new MockResponse('{"createdCount":1}', ['http_code' => 201]),
            new MockResponse('{"code":"invalid_parameter","message":"price"}', ['http_code' => 400]),
            new MockResponse('{"createdCount":1}', ['http_code' => 201]),
        ]), $this->config, $data);

        $results = $handler->handle([
            new Job(11, 1, Job::TYPE_PRODUCT, 1, []),
            new Job(12, 1, Job::TYPE_PRODUCT, 2, []),
            new Job(13, 1, Job::TYPE_PRODUCT, 3, []),
        ]);

        self::assertCount(4, $this->requests);
        self::assertCount(3, $this->requests[0]['body']['products']);
        self::assertSame(Result::DONE, $results[11]->kind);
        self::assertSame(Result::FAIL, $results[12]->kind);
        self::assertSame(Result::DONE, $results[13]->kind);
        self::assertSame('2', $results[12]->request['id']);
    }

    public function testAProductGoneFromTheShopIsSentAsDeleted(): void
    {
        $handler = new CatalogHandler($this->client([new MockResponse('{}', ['http_code' => 201])]), $this->config, new FixtureShopData());
        $handler->handle([new Job(1, 1, Job::TYPE_PRODUCT, 77, [])]);

        self::assertSame('77', $this->requests[0]['body']['products'][0]['id']);
        self::assertTrue($this->requests[0]['body']['products'][0]['isDeleted']);
        self::assertTrue($this->requests[0]['body']['updateEnabled']);
    }

    public function testPartialEventBatchFailsOnlyTheRefusedEvents(): void
    {
        $data = new FixtureShopData();
        $data->orders[501] = OrderMapperTest::order();
        $data->orders[502] = OrderMapperTest::order(['id_order' => 502, 'email' => 'broken']);

        $handler = new EventHandler($this->client([
            new MockResponse('{"message":"partial","count":1,"errors":[{"eventIndex":1,"message":"invalid email_id"}]}', ['http_code' => 207]),
        ]), $this->config, $data);

        $results = $handler->handle([
            new Job(1, 1, Job::TYPE_EVENT, 501, ['name' => EventMapper::ORDER_COMPLETED, 'id_state' => 2]),
            new Job(2, 1, Job::TYPE_EVENT, 502, ['name' => EventMapper::ORDER_COMPLETED, 'id_state' => 2]),
            new Job(3, 1, Job::TYPE_EVENT, 503, ['name' => EventMapper::ORDER_COMPLETED]),
            new Job(4, 1, Job::TYPE_EVENT, 501, ['name' => 'nonsense']),
        ]);

        self::assertSame('https://api.brevo.com/v3/events/batch', $this->requests[0]['url']);
        self::assertCount(2, $this->requests[0]['body']);
        self::assertSame('completed', $this->requests[0]['body'][0]['event_properties']['status']);
        self::assertSame('State 2', $this->requests[0]['body'][0]['event_properties']['state_name']);
        self::assertSame(Result::DONE, $results[1]->kind);
        self::assertSame(Result::FAIL, $results[2]->kind);
        self::assertStringContainsString('invalid email_id', $results[2]->error);
        self::assertSame(Result::DONE, $results[3]->kind, 'a missing order is skipped, not failed');
        self::assertSame(Result::FAIL, $results[4]->kind);
    }

    public function testDoubleOptInNeedsItsSettings(): void
    {
        self::assertNull(DoiHandler::body($this->config, 'zoe@example.com', 'formularz'));

        $this->config->set(Config::LIST_NEWSLETTER, 5);
        $this->config->set(Config::DOI_TEMPLATE, 12);
        $this->config->set(Config::DOI_REDIRECT, 'https://artedeum.pl/content/7-dziekujemy');

        $body = DoiHandler::body($this->config, 'zoe@example.com', 'formularz');

        self::assertSame([5], $body['includeListIds']);
        self::assertSame(12, $body['templateId']);
        self::assertSame('formularz', $body['attributes']['NEWSLETTER_SOURCE']);
        self::assertTrue($body['attributes']['MARKETING_CONSENT']);
    }
}
