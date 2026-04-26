<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Port;

final class AnimalInClinicWithoutActivePatient implements ChipLookupResult
{
    public function __construct(
        public readonly string $animalId,
        public readonly string $animalName,
    ) {
    }
}
