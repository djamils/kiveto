<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListPortalPets;

final readonly class PortalPetView
{
    /**
     * Enumerations are exposed as their stored (lowercase) values.
     */
    public function __construct(
        public string $id,
        public string $primaryOwnerClientId,
        public string $name,
        public string $species,
        public ?string $breedName,
        public bool $isMixedBreed,
        public string $sex,
        public string $reproductiveStatus,
        public ?\DateTimeImmutable $birthDate,
        public string $lifeStatus,
        public ?string $microchipNumber,
    ) {
    }
}
