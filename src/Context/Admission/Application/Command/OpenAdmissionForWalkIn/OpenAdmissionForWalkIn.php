<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Command\OpenAdmissionForWalkIn;

use App\Shared\Application\Bus\CommandInterface;

final readonly class OpenAdmissionForWalkIn implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $triageLevel = 'standard',
        public ?string $knownAnimalId = null,
        public ?string $animalName = null,
        public ?string $provisionalLabel = null,
        public ?string $triageNotes = null,
    ) {
    }
}
