<?php

declare(strict_types=1);

namespace Mzc\Brevo\Queue;

final class WorkerReport
{
    public int $done = 0;
    public int $retried = 0;
    public int $failed = 0;

    /** @var array<int,int> id_shop => seconds the worker stays paused */
    public array $paused = [];

    /** @var string[] */
    public array $errors = [];

    /** Why the run did nothing at all, if it did nothing. */
    public ?string $skipped = null;

    public function summary(): string
    {
        if ($this->skipped !== null) {
            return 'Skipped: ' . $this->skipped;
        }

        $line = sprintf('done %d, retry %d, failed %d', $this->done, $this->retried, $this->failed);

        foreach ($this->paused as $idShop => $seconds) {
            $line .= sprintf('; shop %d paused for %d s', $idShop, $seconds);
        }

        return $line;
    }
}
