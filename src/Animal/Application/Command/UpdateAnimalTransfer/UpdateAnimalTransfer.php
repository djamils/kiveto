<?php

declare(strict_types=1);

namespace App\Animal\Application\Command\UpdateAnimalTransfer;

use App\Shared\Application\Bus\CommandInterface;

final readonly class UpdateAnimalTransfer implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $animalId,
        public string $transferStatus,
        public ?string $soldAt,
        public ?string $givenAt,
    ) {
    }
}
