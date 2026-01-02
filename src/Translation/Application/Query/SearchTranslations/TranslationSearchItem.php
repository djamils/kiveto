<?php

declare(strict_types=1);

namespace App\Translation\Application\Query\SearchTranslations;

final readonly class TranslationSearchItem
{
    public function __construct(
        public string $scope,
        public string $locale,
        public string $domain,
        public string $key,
        public string $value,
        public \DateTimeImmutable $updatedAt,
        public ?string $updatedBy,
    ) {
    }
}
