<?php

declare(strict_types=1);

namespace Mzc\Brevo\Sync;

use Mzc\Brevo\Api\BrevoClient;
use Mzc\Brevo\Api\Json;
use Mzc\Brevo\Api\Response;
use Mzc\Brevo\Cart\RecoverLink;
use Mzc\Brevo\Config;
use Mzc\Brevo\Data\ShopData;
use Mzc\Brevo\Mapper\EventMapper;
use Mzc\Brevo\Queue\Job;
use Mzc\Brevo\Queue\Result;

/**
 * Events for Brevo automations (job type "event"), up to 200 per /v3/events/batch.
 *
 * Cart events go only to contacts with marketing consent while
 * MZCBREVO_CART_REQUIRE_CONSENT is on (the default): in Poland an abandoned-cart
 * e-mail is commercial information. A cart that has since become an order sends
 * nothing — order_completed has already said everything.
 */
class EventHandler extends AbstractHandler
{
    public const BATCH_SIZE = 200;

    /** Brevo's cap is 512 KB per batch request; stay clear of it. */
    public const MAX_BATCH_BYTES = 400000;

    private ShopData $data;

    public function __construct(BrevoClient $client, Config $config, ShopData $data)
    {
        parent::__construct($client, $config);
        $this->data = $data;
    }

    public function handle(array $jobs): array
    {
        $results = [];
        $events = [];

        foreach ($jobs as $job) {
            $built = $this->build($job);

            if ($built instanceof Result) {
                $results[$job->id] = $built;
            } else {
                $events[$job->id] = $built;
            }
        }

        foreach ($this->chunks($events) as $chunk) {
            $results += $this->sendChunk($chunk);
        }

        return $results;
    }

    /**
     * @return array<string,mixed>|Result the event, or a Result when there is nothing to send
     */
    private function build(Job $job)
    {
        $name = (string) ($job->payload['name'] ?? '');
        $timestamp = isset($job->payload['ts']) ? (int) $job->payload['ts'] : null;

        if ($name === EventMapper::CART_UPDATED || $name === EventMapper::CART_ABANDONED) {
            $cart = $this->data->cart($job->entityId);

            if ($cart === null || !$cart['lines'] || ShopData::isAnonymous((string) $cart['email'])) {
                return Result::done(['skipped' => 'cart empty, gone or without e-mail']);
            }

            if ($cart['has_order']) {
                return Result::done(['skipped' => 'cart already ordered']);
            }

            if ($this->config->getBool(Config::CART_REQUIRE_CONSENT)
                && !$this->data->hasMarketingConsent((string) $cart['email'], (int) $cart['id_customer'])) {
                return Result::done(['skipped' => 'no marketing consent']);
            }

            return EventMapper::cartEvent(
                $name,
                $cart,
                RecoverLink::url((int) $cart['id_cart'], (int) $cart['id_shop'], (int) $cart['id_lang']),
                $timestamp
            );
        }

        if ($name === EventMapper::ORDER_COMPLETED || $name === EventMapper::ORDER_STATUS_CHANGED) {
            $order = $this->data->order($job->entityId);

            if ($order === null || ShopData::isAnonymous((string) $order['email'])) {
                return Result::done(['skipped' => 'order not found or anonymized']);
            }

            $idState = (int) ($job->payload['id_state'] ?? $order['current_state']);

            return EventMapper::orderEvent(
                $name,
                $order,
                $this->data->orderEventLines($order),
                $this->config->brevoId((int) $order['id_order']),
                $this->config->statusMap()[$idState] ?? '',
                $this->data->stateName($idState),
                $timestamp
            );
        }

        return Result::fail('Unknown event "' . $name . '"');
    }

    /**
     * @param array<int,array> $events
     *
     * @return iterable<array<int,array>>
     */
    private function chunks(array $events): iterable
    {
        $chunk = [];
        $bytes = 0;

        foreach ($events as $jobId => $event) {
            $size = strlen(Json::encode($event));

            if ($chunk && (count($chunk) >= self::BATCH_SIZE || $bytes + $size > self::MAX_BATCH_BYTES)) {
                yield $chunk;
                $chunk = [];
                $bytes = 0;
            }

            $chunk[$jobId] = $event;
            $bytes += $size;
        }

        if ($chunk) {
            yield $chunk;
        }
    }

    /**
     * 202: all accepted. 207: some refused, listed by their index in the batch.
     * 400: all refused.
     *
     * @param array<int,array> $chunk job id => event
     *
     * @return array<int,Result>
     */
    private function sendChunk(array $chunk): array
    {
        $response = $this->raw(fn (): Response => $this->client->post('events/batch', array_values($chunk)));
        $results = [];
        $jobIds = array_keys($chunk);

        if ($response instanceof Result) {
            foreach ($chunk as $jobId => $event) {
                $results[$jobId] = $response->withRequest($event);
            }

            return $results;
        }

        $refused = [];

        if ($response->status === 207) {
            foreach ((array) ($response->data['errors'] ?? []) as $error) {
                $index = (int) ($error['eventIndex'] ?? -1);

                if (isset($jobIds[$index])) {
                    $refused[$jobIds[$index]] = 'HTTP 207: ' . (string) ($error['message'] ?? 'refused');
                }
            }
        }

        foreach ($chunk as $jobId => $event) {
            if (isset($refused[$jobId])) {
                $results[$jobId] = Result::fail($refused[$jobId], $event);
            } elseif ($response->isSuccess()) {
                $results[$jobId] = Result::done($event);
            } else {
                $results[$jobId] = $this->interpret($response, $event);
            }
        }

        return $results;
    }
}
