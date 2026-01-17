<?php

declare(strict_types=1);

namespace App\Translation\Application\Command\UpsertTranslation;

use App\Shared\Application\Bus\CommandInterface;

final readonly class UpsertTranslation implements CommandInterface
{
    public function __construct(
        public string $scope,
        public string $locale,
        public string $domain,
        public string $key,
        public string $value,
        public ?string $description = null,
        public ?string $actorId = null,
    ) {
    }
}
