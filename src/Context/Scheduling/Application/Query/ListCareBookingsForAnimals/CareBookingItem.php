<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListCareBookingsForAnimals;

final readonly class CareBookingItem
{
    /**
     * @param string $preventiveCareKind vaccination|internal_parasite|external_parasite|other
     */
    public function __construct(
        public string $animalId,
        public \DateTimeImmutable $startsAtUtc,
        public string $preventiveCareKind,
    ) {
    }
}
