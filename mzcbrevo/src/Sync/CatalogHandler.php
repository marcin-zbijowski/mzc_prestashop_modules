<?php

declare(strict_types=1);

namespace Mzc\Brevo\Sync;

use Mzc\Brevo\Api\BrevoClient;
use Mzc\Brevo\Api\Response;
use Mzc\Brevo\Config;
use Mzc\Brevo\Data\ShopData;
use Mzc\Brevo\Mapper\CatalogMapper;
use Mzc\Brevo\Queue\Job;
use Mzc\Brevo\Queue\Result;

/**
 * Products and categories to the Brevo catalogue (job types "product", "category"),
 * 100 per batch request, always as upserts. A product or category that no longer
 * exists in the shop is sent as deleted.
 */
class CatalogHandler extends AbstractHandler
{
    public const BATCH_SIZE = 100;

    private ShopData $data;

    public function __construct(BrevoClient $client, Config $config, ShopData $data)
    {
        parent::__construct($client, $config);
        $this->data = $data;
    }

    public function handle(array $jobs): array
    {
        $products = [];
        $categories = [];
        $brevoId = [$this->config, 'brevoId'];

        foreach ($jobs as $job) {
            if ($job->type === Job::TYPE_PRODUCT) {
                $product = empty($job->payload['deleted']) ? $this->data->product($job->entityId) : null;
                $products[$job->id] = $product !== null
                    ? CatalogMapper::product($product, $brevoId)
                    : CatalogMapper::deleted($this->config->brevoId($job->entityId));
            } else {
                $category = empty($job->payload['deleted']) ? $this->data->category($job->entityId) : null;
                $categories[$job->id] = $category !== null
                    ? CatalogMapper::category($category, $brevoId)
                    : CatalogMapper::deleted($this->config->brevoId($job->entityId));
            }
        }

        return $this->sendProducts($products) + $this->sendCategories($categories);
    }

    /**
     * @param array<int,array> $products job id => product; also used by the import
     *
     * @return array<int,Result>
     */
    public function sendProducts(array $products): array
    {
        return $this->sendInBatches($products, self::BATCH_SIZE, fn (array $batch): Response => $this->client->post('products/batch', [
            'products' => $batch,
            'updateEnabled' => true,
        ]));
    }

    /**
     * @param array<int,array> $categories job id => category; also used by the import
     *
     * @return array<int,Result>
     */
    public function sendCategories(array $categories): array
    {
        return $this->sendInBatches($categories, self::BATCH_SIZE, fn (array $batch): Response => $this->client->post('categories/batch', [
            'categories' => $batch,
            'updateEnabled' => true,
        ]));
    }
}
