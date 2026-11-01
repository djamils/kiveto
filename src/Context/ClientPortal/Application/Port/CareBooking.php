<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** An appointment holding its slot, booked for a motif of a preventive care kind. */
final readonly class CareBooking
{
    public function __construct(
        public string $animalId,
        public \DateTimeImmutable $startsAtUtc,
        public string $preventiveCareKind,
    ) {
    }
}
