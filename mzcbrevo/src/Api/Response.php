<?php

declare(strict_types=1);

namespace Mzc\Brevo\Api;

final class Response
{
    public int $status;

    /** @var array<mixed>|null decoded JSON body, null when empty or not JSON */
    public ?array $data;

    public string $raw;

    /** @var array<string,list<string>> */
    public array $headers;

    public function __construct(int $status, ?array $data, string $raw = '', array $headers = [])
    {
        $this->status = $status;
        $this->data = $data;
        $this->raw = $raw;
        $this->headers = $headers;
    }

    public function isSuccess(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /** Brevo's own error text ("message", sometimes with a "code"), or the raw body. */
    public function errorMessage(): string
    {
        $message = (string) ($this->data['message'] ?? '');
        $code = (string) ($this->data['code'] ?? '');

        if ($message === '' && $code === '') {
            return 'HTTP ' . $this->status . ($this->raw !== '' ? ': ' . mb_substr($this->raw, 0, 500) : '');
        }

        return 'HTTP ' . $this->status . ($code !== '' ? ' ' . $code : '') . ($message !== '' ? ': ' . $message : '');
    }

    /** Seconds from a Retry-After header (Brevo sends seconds), or null. */
    public function retryAfter(): ?int
    {
        foreach (['retry-after', 'x-sib-ratelimit-reset'] as $name) {
            $value = $this->headers[$name][0] ?? null;

            if ($value !== null && ctype_digit(trim((string) $value))) {
                return max(1, (int) $value);
            }
        }

        return null;
    }
}
