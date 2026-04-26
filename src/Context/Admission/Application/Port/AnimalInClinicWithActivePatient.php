<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Port;

final class AnimalInClinicWithActivePatient implements ChipLookupResult
{
    public function __construct(
        public readonly string $animalId,
        public readonly string $patientId,
        public readonly string $animalName,
    ) {
    }
}
