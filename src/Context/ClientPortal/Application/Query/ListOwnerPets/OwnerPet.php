<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListOwnerPets;

use App\Context\ClientPortal\Application\Port\PortalPet;

/** A pet as its owner sees it; enumerations are their lowercase values. */
final readonly class OwnerPet
{
    /**
     * @param string              $species            dog|cat|nac|other
     * @param string              $sex                male|female|unknown
     * @param string              $reproductiveStatus intact|neutered|unknown
     * @param ?\DateTimeImmutable $birthDate          a date, no time of day
     * @param string              $lifeStatus         alive|deceased|missing
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $species,
        public ?string $breedName,
        public bool $isMixedBreed,
        public string $sex,
        public string $reproductiveStatus,
        public ?\DateTimeImmutable $birthDate,
        public string $lifeStatus,
    ) {
    }

    public static function of(PortalPet $pet): self
    {
        return new self(
            $pet->id,
            $pet->name,
            $pet->species,
            $pet->breedName,
            $pet->isMixedBreed,
            $pet->sex,
            $pet->reproductiveStatus,
            $pet->birthDate,
            $pet->lifeStatus,
        );
    }
}
