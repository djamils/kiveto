<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\ExternalApi;

/**
 * A response the adapter has to interpret (2xx or 4xx): its status and its
 * body decoded as JSON, null when the body is not a JSON object or array.
 */
final readonly class ExternalApiResponse
{
    /**
     * @param array<mixed>|null $data
     */
    public function __construct(
        public int $status,
        public ?array $data,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
