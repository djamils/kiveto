<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Command\OpenEmergencyAdmission;

use App\Shared\Application\Bus\CommandInterface;

final readonly class OpenEmergencyAdmission implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $intakeChannel,
        public string $triageLevel,
        public ?string $provisionalLabel = null,
        public ?string $knownAnimalId = null,
        public ?string $animalName = null,
        public ?string $observedSpecies = null,
        public ?string $observedColor = null,
        public ?string $presenterName = null,
        public ?string $presenterPhone = null,
        public ?string $presenterRole = null,
        public ?string $physicalDescription = null,
        public ?string $triageNotes = null,
    ) {
    }
}
