<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetOwnerPet;

use App\Context\ClientPortal\Application\Query\ListOwnerPets\OwnerPet;

final readonly class OwnerPetDetails
{
    /**
     * @param ?string $chipNumberMasked `•••• ` and the last 4 characters of the chip, or null
     * @param ?string $referentVet      the practitioner of the latest report shared with the account
     */
    public function __construct(
        public OwnerPet $pet,
        public ?PetWeight $lastWeight,
        public ?string $chipNumberMasked,
        public ?string $referentVet,
    ) {
    }
}
