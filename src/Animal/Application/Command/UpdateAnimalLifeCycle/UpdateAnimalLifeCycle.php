<?php

declare(strict_types=1);

namespace App\Animal\Application\Command\UpdateAnimalLifeCycle;

use App\Shared\Application\Bus\CommandInterface;

final readonly class UpdateAnimalLifeCycle implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $animalId,
        public string $lifeStatus,
        public ?string $deceasedAt,
        public ?string $missingSince,
    ) {
    }
}
