<?php

declare(strict_types=1);

namespace Mzc\Brevo\Sync;

use Mzc\Brevo\Api\BrevoClient;
use Mzc\Brevo\Config;
use Mzc\Brevo\Data\ShopContext;
use Mzc\Brevo\Data\ShopData;
use Mzc\Brevo\Occasion\OccasionExtractor;
use Mzc\Brevo\Queue\Job;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Builds the handler for a (shop, job type) pair, with that shop's API key, settings
 * and data — and switches the legacy Context to that shop first, since prices and
 * links are read from it.
 */
class HandlerFactory
{
    private ?HttpClientInterface $http;

    /** @var array<int,Config> */
    private array $configs = [];

    private ?int $currentShop = null;

    public function __construct(?HttpClientInterface $http = null)
    {
        $this->http = $http;
    }

    public function config(int $idShop): Config
    {
        return $this->configs[$idShop] ??= new Config($idShop);
    }

    public function client(int $idShop): BrevoClient
    {
        return new BrevoClient($this->config($idShop)->apiKey(), $this->http);
    }

    public function handler(int $idShop, string $type): ?Handler
    {
        if ($this->currentShop !== $idShop) {
            ShopContext::switchTo($idShop);
            $this->currentShop = $idShop;
        }

        $config = $this->config($idShop);
        $client = $this->client($idShop);
        $data = new ShopData($idShop);

        switch ($type) {
            case Job::TYPE_CONTACT:
            case Job::TYPE_UNSUBSCRIBE:
                return new ContactHandler($client, $config, $data, new OccasionExtractor($config->categoryOccasions(), $data->idLang()));
            case Job::TYPE_DELETE:
                return new DeleteContactHandler($client, $config);
            case Job::TYPE_DOI:
                return new DoiHandler($client, $config);
            case Job::TYPE_ORDER:
                return new OrderHandler($client, $config, $data);
            case Job::TYPE_EVENT:
                return new EventHandler($client, $config, $data);
            case Job::TYPE_PRODUCT:
            case Job::TYPE_CATEGORY:
                return new CatalogHandler($client, $config, $data);
            case Job::TYPE_PING:
                return new PingHandler($client, $config);
        }

        return null;
    }
}
