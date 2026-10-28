<?php

declare(strict_types=1);

namespace Mzc\Brevo\Tests\Sync;

use Mzc\Brevo\Api\BrevoClient;
use Mzc\Brevo\Sync\AttributeSynchronizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AttributeSynchronizerTest extends TestCase
{
    /** @var array<int,array{method:string,url:string,body:mixed}> */
    private array $requests = [];

    /**
     * @param array<int,array{name:string,type?:string}> $existing what GET contacts/attributes returns
     */
    private function synchronizer(array $existing): AttributeSynchronizer
    {
        $this->requests = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($existing): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'body' => json_decode($options['body'] ?? 'null', true)];

            return $method === 'GET'
                ? new MockResponse(json_encode(['attributes' => $existing]), ['http_code' => 200])
                : new MockResponse('', ['http_code' => $method === 'POST' ? 201 : 204]);
        });

        return new AttributeSynchronizer(new BrevoClient('k', $http));
    }

    /** @return array{method:string,url:string,body:mixed}|null */
    private function request(string $method, string $suffix): ?array
    {
        foreach ($this->requests as $request) {
            if ($request['method'] === $method && str_ends_with($request['url'], $suffix)) {
                return $request;
            }
        }

        return null;
    }

    public function testCategoryAttributesAreCreatedInTheCategoryCategory(): void
    {
        // Brevo answers 400 "Invalid attribute type for category: normal" when a category-typed
        // attribute is posted to .../attributes/normal/...; it belongs under .../attributes/category/...
        $result = $this->synchronizer([])->sync([1 => 'Visitor', 3 => 'Customer']);

        self::assertTrue($result['ok'], implode("\n", $result['lines']));

        $group = $this->request('POST', 'contacts/attributes/category/PS_GROUP');
        self::assertNotNull($group, 'PS_GROUP must be created under the "category" category');
        self::assertSame([['value' => 1, 'label' => 'Visitor'], ['value' => 3, 'label' => 'Customer']], $group['body']['enumeration']);
        self::assertSame('category', $group['body']['type']);

        $occasion = $this->request('POST', 'contacts/attributes/category/OCCASION_TYPE');
        self::assertNotNull($occasion, 'OCCASION_TYPE must be created under the "category" category');
        self::assertSame([1, 2, 3, 4, 5], array_column($occasion['body']['enumeration'], 'value'));

        self::assertNull($this->request('POST', 'contacts/attributes/normal/PS_GROUP'));
        self::assertNull($this->request('POST', 'contacts/attributes/normal/OCCASION_TYPE'));
    }

    public function testPlainAttributesStayInTheNormalCategory(): void
    {
        $this->synchronizer([])->sync([]);

        self::assertSame('float', $this->request('POST', 'contacts/attributes/normal/ORDERS_COUNT')['body']['type']);
        self::assertSame('boolean', $this->request('POST', 'contacts/attributes/normal/MARKETING_CONSENT')['body']['type']);
        self::assertSame('date', $this->request('POST', 'contacts/attributes/normal/CONSENT_DATE')['body']['type']);
    }

    public function testExistingCategoryAttributesHaveTheirValuesRefreshedInTheCategoryCategory(): void
    {
        $result = $this->synchronizer([
            ['name' => 'PS_GROUP', 'type' => 'category'],
            ['name' => 'OCCASION_TYPE', 'type' => 'category'],
        ])->sync([2 => 'Guest']);

        self::assertTrue($result['ok'], implode("\n", $result['lines']));

        $update = $this->request('PUT', 'contacts/attributes/category/PS_GROUP');
        self::assertNotNull($update, 'refreshing the values must also go through the "category" category');
        self::assertSame([['value' => 2, 'label' => 'Guest']], $update['body']['enumeration']);
        self::assertNotNull($this->request('PUT', 'contacts/attributes/category/OCCASION_TYPE'));
        self::assertNull($this->request('PUT', 'contacts/attributes/normal/PS_GROUP'));
    }

    public function testExistingPlainAttributesAreLeftAlone(): void
    {
        $this->synchronizer([['name' => 'ORDERS_COUNT', 'type' => 'float']])->sync([]);

        self::assertNull($this->request('POST', 'contacts/attributes/normal/ORDERS_COUNT'));
        self::assertNull($this->request('PUT', 'contacts/attributes/normal/ORDERS_COUNT'));
    }
}
