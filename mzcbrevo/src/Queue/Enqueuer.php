<?php

declare(strict_types=1);

namespace Mzc\Brevo\Queue;

use Mzc\Brevo\Config;
use Mzc\Brevo\Mapper\EventMapper;

/**
 * What the hooks call: one method per thing that happened in the shop, each a single
 * INSERT into the queue — never an HTTP request on the customer's time.
 *
 * Each stream (contacts, orders, carts, catalogue) has its own switch, so they can be
 * turned on one at a time. A switched-off stream queues nothing; the historical
 * import is what catches up on the time before.
 */
class Enqueuer
{
    /** cart_updated goes at most once per this many seconds per cart (a debounce: see QueueRepository::enqueue()). */
    public const CART_UPDATE_DELAY = 300;

    private QueueRepository $queue;
    private Config $config;
    private int $idShop;

    public function __construct(Config $config, int $idShop, ?QueueRepository $queue = null)
    {
        $this->config = $config;
        $this->idShop = $idShop;
        $this->queue = $queue ?? new QueueRepository();
    }

    public function contact(int $idCustomer, ?string $newsletterSource = null): void
    {
        if ($idCustomer <= 0 || !$this->config->isStreamEnabled(Config::STREAM_CONTACTS)) {
            return;
        }

        $payload = $newsletterSource !== null ? ['newsletter_source' => $newsletterSource] : [];
        $this->queue->enqueue(Job::TYPE_CONTACT, $idCustomer, 'contact:' . $idCustomer, $payload, $this->idShop);
    }

    public function deleteContact(int $idCustomer, string $email): void
    {
        if ($email === '' || !$this->config->isStreamEnabled(Config::STREAM_CONTACTS)) {
            return;
        }

        $this->queue->enqueue(Job::TYPE_DELETE, $idCustomer, 'delete:' . strtolower($email), ['email' => $email], $this->idShop);
    }

    public function doubleOptIn(string $email, string $source): void
    {
        if ($email === '' || !$this->config->isStreamEnabled(Config::STREAM_CONTACTS)) {
            return;
        }

        $this->queue->enqueue(Job::TYPE_DOI, 0, 'doi:' . strtolower($email), ['email' => $email, 'source' => $source], $this->idShop);
    }

    public function unsubscribe(string $email): void
    {
        if ($email === '' || !$this->config->isStreamEnabled(Config::STREAM_CONTACTS)) {
            return;
        }

        $this->queue->enqueue(Job::TYPE_UNSUBSCRIBE, 0, 'unsubscribe:' . strtolower($email), ['email' => $email], $this->idShop);
    }

    /**
     * An order was placed (with $event = order_completed) or changed state
     * (order_status_changed). The order itself is deduplicated: the worker sends
     * its latest state once. Every status change is its own event.
     */
    public function order(int $idOrder, string $event, int $idState): void
    {
        if ($idOrder <= 0 || !$this->config->isStreamEnabled(Config::STREAM_ORDERS)) {
            return;
        }

        $this->queue->enqueue(Job::TYPE_ORDER, $idOrder, 'order:' . $idOrder, [], $this->idShop);
        $this->queue->enqueue(
            Job::TYPE_EVENT,
            $idOrder,
            'event:' . $event . ':' . $idOrder . ($event === EventMapper::ORDER_STATUS_CHANGED ? ':' . $idState : ''),
            ['name' => $event, 'id_state' => $idState, 'ts' => time()],
            $this->idShop
        );
    }

    public function cartUpdated(int $idCart): void
    {
        if ($idCart <= 0 || !$this->config->isStreamEnabled(Config::STREAM_CARTS)) {
            return;
        }

        $this->queue->enqueue(
            Job::TYPE_EVENT,
            $idCart,
            'event:' . EventMapper::CART_UPDATED . ':' . $idCart,
            ['name' => EventMapper::CART_UPDATED, 'ts' => time()],
            $this->idShop,
            self::CART_UPDATE_DELAY
        );
    }

    public function product(int $idProduct, bool $deleted = false): void
    {
        if ($idProduct <= 0 || !$this->config->isStreamEnabled(Config::STREAM_CATALOG)) {
            return;
        }

        $this->queue->enqueue(Job::TYPE_PRODUCT, $idProduct, 'product:' . $idProduct, $deleted ? ['deleted' => true] : [], $this->idShop);
    }

    public function category(int $idCategory, bool $deleted = false): void
    {
        if ($idCategory <= 0 || !$this->config->isStreamEnabled(Config::STREAM_CATALOG)) {
            return;
        }

        $this->queue->enqueue(Job::TYPE_CATEGORY, $idCategory, 'category:' . $idCategory, $deleted ? ['deleted' => true] : [], $this->idShop);
    }

    /** The back office's test job: goes through the queue whatever the stream switches say. */
    public function ping(): void
    {
        $this->queue->enqueue(Job::TYPE_PING, 0, null, [], $this->idShop);
    }
}
