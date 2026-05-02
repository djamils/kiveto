<?php

declare(strict_types=1);

namespace App\Shared\Search\Query;

use App\Shared\Search\Normalization\SearchTermNormalizer;

final readonly class SearchQuery
{
    public function __construct(
        public string $term,
        public string $clinicId,
        public string $userId,
        public int $limit = 10,
    ) {
    }

    public function normalizedTerm(): string
    {
        return (new SearchTermNormalizer())->normalizeText($this->term);
    }

    public function isExecutable(): bool
    {
        return mb_strlen(trim($this->term)) >= 2;
    }

    public function withLimit(int $limit): self
    {
        return new self($this->term, $this->clinicId, $this->userId, $limit);
    }
}
