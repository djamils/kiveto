<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

/**
 * The animal a patient is reconciled with and that animal's active primary
 * owner; both null for a patient not linked to an animal yet.
 */
final readonly class PatientAnimalOwnership
{
    public function __construct(
        public ?string $animalId,
        public ?string $primaryOwnerClientId,
    ) {
    }
}
