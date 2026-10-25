<?php

declare(strict_types=1);

namespace Mzc\Brevo\Sync;

use Mzc\Brevo\Api\BrevoClient;
use Mzc\Brevo\Api\Response;
use Mzc\Brevo\Config;
use Mzc\Brevo\Data\ShopData;
use Mzc\Brevo\Mapper\OrderMapper;
use Mzc\Brevo\Queue\Result;

/**
 * Live orders to Brevo eCommerce (job type "order"), in batches of up to 1000 with
 * `historical: false` — these orders may start automations. The historical import
 * uses the same mapper with `historical: true` (see Command\ImportCommand).
 *
 * The order is read when the worker sends it, so a placed order and its first status
 * changes, queued under one dedup key, go as one request with the latest state.
 */
class OrderHandler extends AbstractHandler
{
    public const BATCH_SIZE = 1000;

    private ShopData $data;

    public function __construct(BrevoClient $client, Config $config, ShopData $data)
    {
        parent::__construct($client, $config);
        $this->data = $data;
    }

    public function handle(array $jobs): array
    {
        $results = [];
        $items = [];
        $statusMap = $this->config->statusMap();
        $brevoId = [$this->config, 'brevoId'];

        foreach ($jobs as $job) {
            $order = $this->data->order($job->entityId);

            if ($order === null) {
                $results[$job->id] = Result::done(['skipped' => 'order not found']);

                continue;
            }

            $mapped = OrderMapper::toOrder($order, $statusMap, $brevoId);

            if ($mapped === null) {
                $results[$job->id] = Result::done(['skipped' => 'state ' . (int) $order['current_state'] . ' is not mapped to a Brevo status']);

                continue;
            }

            $items[$job->id] = $mapped;
        }

        return $results + $this->sendInBatches(
            $items,
            self::BATCH_SIZE,
            fn (array $orders): Response => $this->client->post('orders/status/batch', [
                'orders' => $orders,
                'historical' => false,
            ])
        );
    }
}
