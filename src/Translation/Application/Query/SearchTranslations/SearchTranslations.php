<?php

declare(strict_types=1);

namespace App\Translation\Application\Query\SearchTranslations;

readonly class SearchTranslations
{
    public function __construct(
        public ?string $scope = null,
        public ?string $locale = null,
        public ?string $domain = null,
        public ?string $keyContains = null,
        public ?string $valueContains = null,
        public ?string $updatedBy = null,
        public ?\DateTimeImmutable $updatedAfter = null,
        public ?string $createdBy = null,
        public ?\DateTimeImmutable $createdAfter = null,
        public int $page = 1,
        public int $perPage = 50,
    ) {
    }
}
