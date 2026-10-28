<?php

declare(strict_types=1);

namespace Mzc\Brevo\Queue;

/**
 * What happened to one job: sent, worth another try, or refused for good.
 */
final class Result
{
    public const DONE = 'done';
    public const RETRY = 'retry';
    public const FAIL = 'fail';

    public string $kind;
    public string $error;

    /** @var array<mixed>|null the request body actually sent, kept on the row for diagnosis */
    public ?array $request;

    private function __construct(string $kind, string $error = '', ?array $request = null)
    {
        $this->kind = $kind;
        $this->error = $error;
        $this->request = $request;
    }

    public static function done(?array $request = null): self
    {
        return new self(self::DONE, '', $request);
    }

    public static function retry(string $error, ?array $request = null): self
    {
        return new self(self::RETRY, $error, $request);
    }

    public static function fail(string $error, ?array $request = null): self
    {
        return new self(self::FAIL, $error, $request);
    }

    /** The same outcome, recorded against one job's part of a batch request. */
    public function withRequest(?array $request): self
    {
        return new self($this->kind, $this->error, $request);
    }
}
