<?php

declare(strict_types=1);

namespace App\Shared\Search\Result;

final readonly class SearchHit
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $id,
        public string $resourceId,
        public string $title,
        public ?string $subtitle,
        public ?string $context,
        public array $metadata = [],
    ) {
    }
}
