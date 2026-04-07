<?php

declare(strict_types=1);

namespace App\Animal\Application\Query\GetAnimalById;

final readonly class OwnershipDto
{
    public function __construct(
        public string $clientId,
        public string $role,
        public string $status,
        public string $startedAt,
        public ?string $endedAt,
    ) {
    }
}
