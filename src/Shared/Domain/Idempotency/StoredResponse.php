<?php

declare(strict_types=1);

namespace App\Shared\Domain\Idempotency;

/**
 * The response recorded for an idempotency key and replayed as-is: HTTP status
 * and raw body (null when the response had no body, e.g. 204).
 */
final class StoredResponse
{
    private readonly int $status;

    private readonly ?string $body;

    private function __construct(int $status, ?string $body)
    {
        $this->status = $status;
        $this->body   = $body;
    }

    public static function create(int $status, ?string $body): self
    {
        if ($status < 100 || $status > 599) {
            throw new \InvalidArgumentException(\sprintf('Invalid HTTP status %d.', $status));
        }

        return new self($status, '' === $body ? null : $body);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): ?string
    {
        return $this->body;
    }
}
