<?php

declare(strict_types=1);

namespace Mzc\Brevo\Tests\Api;

use Mzc\Brevo\Api\BrevoClient;
use Mzc\Brevo\Api\TransportException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException as SymfonyTransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BrevoClientTest extends TestCase
{
    public function testSendsKeyAndJsonBody(): void
    {
        $seen = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = compact('method', 'url', 'options');

            return new MockResponse('{"id": 42}', ['http_code' => 201]);
        });

        $response = (new BrevoClient('xkeysib-test', $http))->post('contacts', ['email' => 'zoe@example.com', 'attributes' => ['TOTAL_SPENT' => 10.0]]);

        self::assertSame('POST', $seen['method']);
        self::assertSame('https://api.brevo.com/v3/contacts', $seen['url']);
        self::assertContains('api-key: xkeysib-test', $seen['options']['headers']);
        self::assertSame('{"email":"zoe@example.com","attributes":{"TOTAL_SPENT":10.0}}', $seen['options']['body']);
        self::assertSame(201, $response->status);
        self::assertTrue($response->isSuccess());
        self::assertSame(['id' => 42], $response->data);
    }

    public function testEncodesContactIdentifiersAndQuery(): void
    {
        $url = null;
        $http = new MockHttpClient(static function (string $method, string $u) use (&$url): MockResponse {
            $url = $u;

            return new MockResponse('', ['http_code' => 204]);
        });

        (new BrevoClient('k', $http))->put(BrevoClient::contactPath('a+b@example.com'), ['attributes' => []], ['identifierType' => 'email_id']);

        self::assertSame('https://api.brevo.com/v3/contacts/a%2Bb%40example.com?identifierType=email_id', $url);
    }

    public function testErrorsAreResponsesNotExceptions(): void
    {
        $http = new MockHttpClient(new MockResponse('{"code":"invalid_parameter","message":"Invalid phone number"}', [
            'http_code' => 400,
        ]));

        $response = (new BrevoClient('k', $http))->post('contacts', []);

        self::assertFalse($response->isSuccess());
        self::assertSame('HTTP 400 invalid_parameter: Invalid phone number', $response->errorMessage());
    }

    public function testRetryAfterHeader(): void
    {
        $http = new MockHttpClient(new MockResponse('', ['http_code' => 429, 'response_headers' => ['Retry-After: 17']]));

        self::assertSame(17, (new BrevoClient('k', $http))->get('account')->retryAfter());
    }

    public function testNetworkFailureThrows(): void
    {
        $http = new MockHttpClient(static function (): MockResponse {
            throw new SymfonyTransportException('Could not resolve host');
        });

        $this->expectException(TransportException::class);
        (new BrevoClient('k', $http))->get('account');
    }
}
