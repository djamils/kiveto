<?php

declare(strict_types=1);

namespace App\Context\Patient\Application\Command\ReconcilePatientToAnimal;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ReconcilePatientToAnimal implements CommandInterface
{
    public function __construct(
        public string $sourcePatientId,
        public string $animalId,
        public string $animalName,
        public string $clinicId,
    ) {
    }
}
