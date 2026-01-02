<?php

declare(strict_types=1);

namespace App\Translation\Application\Command\DeleteTranslation;

readonly class DeleteTranslation
{
    public function __construct(
        public string $scope,
        public string $locale,
        public string $domain,
        public string $key,
        public ?string $actorId = null,
    ) {
    }
}
