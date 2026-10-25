<?php

declare(strict_types=1);

namespace Mzc\Brevo\Api;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A thin client for the Brevo API v3.
 *
 * Deliberately not the official SDK: one Composer dependency less, and full control
 * over timeouts. The client never decides what a response means — it returns every
 * HTTP status as a Response and leaves retry / fail / pause to the queue (see
 * Queue\Outcome). Only a request that never got an answer throws.
 */
class BrevoClient
{
    public const BASE_URL = 'https://api.brevo.com/v3/';

    private HttpClientInterface $http;
    private string $apiKey;
    private string $baseUrl;

    public function __construct(string $apiKey, ?HttpClientInterface $http = null, string $baseUrl = self::BASE_URL)
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
        $this->http = $http ?? HttpClient::create([
            'timeout' => 10,
            'max_duration' => 30,
        ]);
    }

    /**
     * @param array<string,mixed>|list<mixed>|null $body JSON body; null sends none
     * @param array<string,scalar> $query
     *
     * @throws TransportException when Brevo could not be reached at all
     */
    public function request(string $method, string $path, ?array $body = null, array $query = []): Response
    {
        $options = [
            'headers' => [
                'api-key' => $this->apiKey,
                'accept' => 'application/json',
            ],
        ];

        if ($body !== null) {
            $options['headers']['content-type'] = 'application/json';
            $options['body'] = Json::encode($body);
        }

        if ($query) {
            $options['query'] = $query;
        }

        try {
            $response = $this->http->request($method, $this->baseUrl . ltrim($path, '/'), $options);
            $status = $response->getStatusCode();
            $content = $response->getContent(false);
            $headers = $response->getHeaders(false);
        } catch (TransportExceptionInterface $e) {
            throw new TransportException($method . ' ' . $path . ': ' . $e->getMessage(), 0, $e);
        }

        $data = $content === '' ? null : json_decode($content, true);

        return new Response($status, is_array($data) ? $data : null, $content, $headers);
    }

    public function get(string $path, array $query = []): Response
    {
        return $this->request('GET', $path, null, $query);
    }

    public function post(string $path, array $body): Response
    {
        return $this->request('POST', $path, $body);
    }

    public function put(string $path, array $body, array $query = []): Response
    {
        return $this->request('PUT', $path, $body, $query);
    }

    public function delete(string $path, array $query = []): Response
    {
        return $this->request('DELETE', $path, null, $query);
    }

    /** Path segment for a contact identifier (an e-mail address contains "@" and maybe "+"). */
    public static function contactPath(string $identifier): string
    {
        return 'contacts/' . rawurlencode($identifier);
    }
}
