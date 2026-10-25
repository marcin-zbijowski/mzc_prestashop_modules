<?php

declare(strict_types=1);

namespace Mzc\Brevo\Queue;

/**
 * One row of the queue table, as the worker sees it.
 */
final class Job
{
    public const TYPE_CONTACT = 'contact';
    public const TYPE_UNSUBSCRIBE = 'unsubscribe';
    public const TYPE_DELETE = 'delete';
    public const TYPE_DOI = 'doi';
    public const TYPE_ORDER = 'order';
    public const TYPE_EVENT = 'event';
    public const TYPE_PRODUCT = 'product';
    public const TYPE_CATEGORY = 'category';
    public const TYPE_PING = 'ping';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DEAD = 'dead';

    public int $id;
    public int $idShop;
    public string $type;
    public int $entityId;

    /** @var array<string,mixed> */
    public array $payload;

    public int $attempts;

    public function __construct(int $id, int $idShop, string $type, int $entityId, array $payload, int $attempts = 0)
    {
        $this->id = $id;
        $this->idShop = $idShop;
        $this->type = $type;
        $this->entityId = $entityId;
        $this->payload = $payload;
        $this->attempts = $attempts;
    }

    public static function fromRow(array $row): self
    {
        $payload = json_decode((string) ($row['payload'] ?? ''), true);

        return new self(
            (int) $row['id_mzcbrevo_queue'],
            (int) $row['id_shop'],
            (string) $row['type'],
            (int) $row['entity_id'],
            is_array($payload) ? $payload : [],
            (int) $row['attempts']
        );
    }
}
