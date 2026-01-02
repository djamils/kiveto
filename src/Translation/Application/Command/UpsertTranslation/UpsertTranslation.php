<?php

declare(strict_types=1);

namespace App\Translation\Application\Command\UpsertTranslation;

readonly class UpsertTranslation
{
    public function __construct(
        public string $scope,
        public string $locale,
        public string $domain,
        public string $key,
        public string $value,
        public ?string $actorId = null,
    ) {
    }
}
