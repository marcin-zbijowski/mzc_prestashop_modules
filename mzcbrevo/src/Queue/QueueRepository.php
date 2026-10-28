<?php

declare(strict_types=1);

namespace Mzc\Brevo\Queue;

use Db;
use Mzc\Brevo\Api\Json;

/**
 * The queue table: everything bound for Brevo passes through here.
 *
 * Deduplication: `dedup_key` is UNIQUE, and only a pending row holds one. Enqueueing
 * a job whose key is already pending replaces its payload and keeps its schedule, so
 * fifty actionCartSave calls in a minute end as one row with the last state. The
 * worker clears the key when it claims a row, which is what lets the next change
 * of the same cart start a new row instead of overwriting one already in flight.
 * MySQL allows any number of NULLs under a UNIQUE index.
 *
 * Times are written from PHP (the shop's timezone), never with NOW(), so they
 * compare consistently whatever the database server's own timezone is.
 */
class QueueRepository
{
    public const TABLE = 'mzcbrevo_queue';

    /** A row left in "processing" longer than this belongs to a worker that died. */
    public const STALE_SECONDS = 900;

    /** How long finished rows are kept for diagnosis. */
    public const KEEP_DONE_DAYS = 30;

    private Db $db;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? Db::getInstance();
    }

    public static function table(): string
    {
        return _DB_PREFIX_ . self::TABLE;
    }

    /**
     * Adds a job, or refreshes the pending one with the same dedup key.
     *
     * @param int $delaySeconds for a new row only: a refresh keeps the original
     *                          schedule, which turns the delay into a debounce
     */
    public function enqueue(
        string $type,
        int $entityId,
        ?string $dedupKey,
        array $payload,
        int $idShop,
        int $delaySeconds = 0
    ): bool {
        $now = self::now();
        $json = Json::encode($payload);

        return (bool) $this->db->execute(
            'INSERT INTO `' . self::table() . '`
                (`id_shop`, `type`, `entity_id`, `dedup_key`, `payload`, `status`, `attempts`, `next_attempt_at`, `created_at`, `updated_at`)
            VALUES (
                ' . $idShop . ',
                \'' . pSQL($type) . '\',
                ' . $entityId . ',
                ' . ($dedupKey === null ? 'NULL' : '\'' . pSQL($idShop . ':' . $dedupKey) . '\'') . ',
                \'' . pSQL((string) $json, true) . '\',
                \'' . Job::STATUS_PENDING . '\',
                0,
                \'' . self::at(time() + $delaySeconds) . '\',
                \'' . $now . '\',
                \'' . $now . '\'
            )
            ON DUPLICATE KEY UPDATE `payload` = VALUES(`payload`), `updated_at` = VALUES(`updated_at`)'
        );
    }

    /**
     * Takes up to $limit due jobs, oldest first, and marks them as in flight.
     *
     * Only safe under the worker lock (see acquireLock()): two workers would claim
     * the same rows.
     *
     * @return Job[]
     */
    public function claim(int $limit): array
    {
        $rows = $this->db->executeS(
            'SELECT * FROM `' . self::table() . '`
            WHERE `status` = \'' . Job::STATUS_PENDING . '\' AND `next_attempt_at` <= \'' . self::now() . '\'
            ORDER BY `id_mzcbrevo_queue` ASC
            LIMIT ' . max(1, $limit)
        ) ?: [];

        if (!$rows) {
            return [];
        }

        $ids = array_map(static fn (array $row): int => (int) $row['id_mzcbrevo_queue'], $rows);

        $this->db->execute(
            'UPDATE `' . self::table() . '`
            SET `status` = \'' . Job::STATUS_PROCESSING . '\', `dedup_key` = NULL, `updated_at` = \'' . self::now() . '\'
            WHERE `id_mzcbrevo_queue` IN (' . implode(',', $ids) . ')'
        );

        return array_map([Job::class, 'fromRow'], $rows);
    }

    public function markDone(Job $job, ?array $request = null): void
    {
        $this->update($job->id, [
            'status' => Job::STATUS_DONE,
            'last_error' => null,
            'last_request' => $request,
        ]);
    }

    /** A 4xx: the data is wrong, another attempt would get the same answer. */
    public function markFailed(Job $job, string $error, ?array $request = null): void
    {
        $this->update($job->id, [
            'status' => Job::STATUS_FAILED,
            'attempts' => $job->attempts + 1,
            'last_error' => $error,
            'last_request' => $request,
        ]);
    }

    /**
     * Schedules another attempt, or buries the job once RetryPolicy says so.
     *
     * @param bool $countsAsAttempt false for throttling: the job itself was fine
     */
    public function markRetry(Job $job, string $error, bool $countsAsAttempt = true, ?array $request = null, int $minDelay = 0): void
    {
        $attempts = $job->attempts + ($countsAsAttempt ? 1 : 0);
        $delay = $countsAsAttempt ? RetryPolicy::delayAfter($attempts) : 0;

        if ($delay === null) {
            $this->update($job->id, [
                'status' => Job::STATUS_DEAD,
                'attempts' => $attempts,
                'last_error' => $error,
                'last_request' => $request,
            ]);

            return;
        }

        $this->update($job->id, [
            'status' => Job::STATUS_PENDING,
            'attempts' => $attempts,
            'next_attempt_at' => self::at(time() + max($delay, $minDelay)),
            'last_error' => $error,
            'last_request' => $request,
        ]);
    }

    /** Back to pending, as if never claimed. */
    public function release(Job $job, int $delaySeconds = 0): void
    {
        $this->update($job->id, [
            'status' => Job::STATUS_PENDING,
            'next_attempt_at' => self::at(time() + $delaySeconds),
        ]);
    }

    /** The back office's "retry" on a failed or dead row: a fresh start. */
    public function retry(int $id): void
    {
        $this->db->execute(
            'UPDATE `' . self::table() . '`
            SET `status` = \'' . Job::STATUS_PENDING . '\', `attempts` = 0,
                `next_attempt_at` = \'' . self::now() . '\', `updated_at` = \'' . self::now() . '\'
            WHERE `id_mzcbrevo_queue` = ' . $id . '
            AND `status` IN (\'' . Job::STATUS_FAILED . '\', \'' . Job::STATUS_DEAD . '\')'
        );
    }

    public function retryAll(?int $idShop = null): int
    {
        $this->db->execute(
            'UPDATE `' . self::table() . '`
            SET `status` = \'' . Job::STATUS_PENDING . '\', `attempts` = 0,
                `next_attempt_at` = \'' . self::now() . '\', `updated_at` = \'' . self::now() . '\'
            WHERE `status` IN (\'' . Job::STATUS_FAILED . '\', \'' . Job::STATUS_DEAD . '\')'
            . ($idShop !== null ? ' AND `id_shop` = ' . $idShop : '')
        );

        return (int) $this->db->Affected_Rows();
    }

    /** Rows of a worker that died mid-run go back to pending. */
    public function resetStale(): void
    {
        $this->db->execute(
            'UPDATE `' . self::table() . '`
            SET `status` = \'' . Job::STATUS_PENDING . '\'
            WHERE `status` = \'' . Job::STATUS_PROCESSING . '\'
            AND `updated_at` < \'' . self::at(time() - self::STALE_SECONDS) . '\''
        );
    }

    public function cleanup(): void
    {
        $this->db->execute(
            'DELETE FROM `' . self::table() . '`
            WHERE `status` = \'' . Job::STATUS_DONE . '\'
            AND `updated_at` < \'' . self::at(time() - self::KEEP_DONE_DAYS * 86400) . '\'
            LIMIT 5000'
        );
    }

    /**
     * @return array<string,int> status => number of rows
     */
    public function counts(?int $idShop = null): array
    {
        $counts = array_fill_keys(
            [Job::STATUS_PENDING, Job::STATUS_PROCESSING, Job::STATUS_DONE, Job::STATUS_FAILED, Job::STATUS_DEAD],
            0
        );

        $rows = $this->db->executeS(
            'SELECT `status`, COUNT(*) AS n FROM `' . self::table() . '`'
            . ($idShop !== null ? ' WHERE `id_shop` = ' . $idShop : '')
            . ' GROUP BY `status`'
        ) ?: [];

        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }

        return $counts;
    }

    /**
     * Failed and dead rows, newest first, for the back office.
     */
    public function problems(int $limit = 100, ?int $idShop = null): array
    {
        return $this->db->executeS(
            'SELECT `id_mzcbrevo_queue`, `type`, `entity_id`, `status`, `attempts`, `last_error`, `payload`, `last_request`, `created_at`, `updated_at`
            FROM `' . self::table() . '`
            WHERE `status` IN (\'' . Job::STATUS_FAILED . '\', \'' . Job::STATUS_DEAD . '\')'
            . ($idShop !== null ? ' AND `id_shop` = ' . $idShop : '')
            . ' ORDER BY `updated_at` DESC LIMIT ' . max(1, $limit)
        ) ?: [];
    }

    /** Age in seconds of the oldest job waiting to be sent, null when nothing waits. */
    public function oldestPendingAge(?int $idShop = null): ?int
    {
        $oldest = $this->db->getValue(
            'SELECT MIN(`created_at`) FROM `' . self::table() . '`
            WHERE `status` = \'' . Job::STATUS_PENDING . '\' AND `attempts` = 0'
            . ($idShop !== null ? ' AND `id_shop` = ' . $idShop : '')
        );

        if (!$oldest) {
            return null;
        }

        return max(0, time() - (int) strtotime((string) $oldest));
    }

    /**
     * One worker at a time, without a lock file: a MySQL named lock, released by the
     * server itself if the PHP process dies.
     */
    public function acquireLock(): bool
    {
        return (int) $this->db->getValue('SELECT GET_LOCK(\'' . pSQL(self::lockName()) . '\', 0)') === 1;
    }

    public function releaseLock(): void
    {
        $this->db->getValue('SELECT RELEASE_LOCK(\'' . pSQL(self::lockName()) . '\')');
    }

    private static function lockName(): string
    {
        return _DB_NAME_ . '.' . _DB_PREFIX_ . 'mzcbrevo_worker';
    }

    private function update(int $id, array $fields): void
    {
        $set = ['`updated_at` = \'' . self::now() . '\''];

        foreach ($fields as $column => $value) {
            if (in_array($column, ['last_request'], true) && $value !== null) {
                $value = Json::encode($value);
            }

            if ($column === 'last_error' && $value !== null) {
                $value = mb_substr((string) $value, 0, 2000);
            }

            if ($value === null) {
                $set[] = '`' . $column . '` = NULL';
            } elseif (is_int($value)) {
                $set[] = '`' . $column . '` = ' . $value;
            } else {
                $set[] = '`' . $column . '` = \'' . pSQL((string) $value, true) . '\'';
            }
        }

        $this->db->execute(
            'UPDATE `' . self::table() . '` SET ' . implode(', ', $set) . ' WHERE `id_mzcbrevo_queue` = ' . $id
        );
    }

    public static function now(): string
    {
        return self::at(time());
    }

    public static function at(int $timestamp): string
    {
        return date('Y-m-d H:i:s', $timestamp);
    }
}
