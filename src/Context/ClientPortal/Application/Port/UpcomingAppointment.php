<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** A planned appointment of an animal, without its free-text reason or notes. */
final readonly class UpcomingAppointment
{
    /**
     * @param string $category consult|urg|vaccin|suivi|bilan|chir, read from the reason by Scheduling
     */
    public function __construct(
        public string $id,
        public string $animalId,
        public \DateTimeImmutable $startsAtUtc,
        public string $practitionerUserId,
        public string $category,
    ) {
    }
}
