<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetOwnerPet;

final readonly class PetWeight
{
    /**
     * @param \DateTimeImmutable $at the clinic-local day of the measurement, no time of day
     */
    public function __construct(
        public int $weightGrams,
        public \DateTimeImmutable $at,
    ) {
    }
}
