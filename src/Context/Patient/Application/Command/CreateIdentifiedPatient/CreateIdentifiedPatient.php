<?php

declare(strict_types=1);

namespace App\Context\Patient\Application\Command\CreateIdentifiedPatient;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CreateIdentifiedPatient implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $animalId,
        public string $animalName,
    ) {
    }
}
