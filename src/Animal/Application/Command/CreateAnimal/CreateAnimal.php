<?php

declare(strict_types=1);

namespace App\Animal\Application\Command\CreateAnimal;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CreateAnimal implements CommandInterface
{
    /**
     * @param list<string> $secondaryOwnerClientIds
     */
    public function __construct(
        public string $clinicId,
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
        public string $lifeStatus,
        public ?string $deceasedAt,
        public ?string $missingSince,
        public string $transferStatus,
        public ?string $soldAt,
        public ?string $givenAt,
        public ?string $auxiliaryContactFirstName,
        public ?string $auxiliaryContactLastName,
        public ?string $auxiliaryContactPhoneNumber,
        public string $primaryOwnerClientId,
        public array $secondaryOwnerClientIds,
    ) {
    }
}
