<?php

declare(strict_types=1);

namespace App\Animal\Application\Command\UpdateAnimalIdentity;

use App\Shared\Application\Bus\CommandInterface;

final readonly class UpdateAnimalIdentity implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $animalId,
        public string $name,
        public string $species,
        public string $sex,
        public string $reproductiveStatus,
        public bool $isMixedBreed,
        public ?string $breedName,
        public ?string $birthDate,
        public ?string $color,
        public ?string $photoUrl,
        public ?string $microchipNumber,
        public ?string $tattooNumber,
        public ?string $passportNumber,
        public string $registryType,
        public ?string $registryNumber,
        public ?string $sireNumber,
        public ?string $auxiliaryContactFirstName,
        public ?string $auxiliaryContactLastName,
        public ?string $auxiliaryContactPhoneNumber,
    ) {
    }
}
