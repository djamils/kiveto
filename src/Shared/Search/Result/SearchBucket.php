<?php

declare(strict_types=1);

namespace App\Shared\Search\Result;

final readonly class SearchBucket
{
    /**
     * @param list<SearchHit> $hits
     */
    public function __construct(
        public string $type,
        public array $hits,
        public bool $hasMore,
    ) {
    }
}
