<?php

declare(strict_types=1);

namespace App\Translation\Application\Command\DeleteTranslation;

use App\Shared\Application\Bus\CommandInterface;

final readonly class DeleteTranslation implements CommandInterface
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
