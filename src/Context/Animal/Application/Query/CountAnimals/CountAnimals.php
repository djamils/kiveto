<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\CountAnimals;

use App\Shared\Application\Bus\QueryInterface;

final readonly class CountAnimals implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public ?string $searchTerm = null,
        public ?string $status = null,
        public ?string $species = null,
        public ?string $lifeStatus = null,
        public ?string $ownerClientId = null,
    ) {
    }
}
