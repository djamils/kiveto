<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** The appointments of the owner's animals (Scheduling context). */
interface OwnerAppointmentsInterface
{
    /**
     * The earliest planned appointment of one of the animals starting at or
     * after now, or null.
     *
     * @param list<string> $animalIds
     */
    public function nextFor(string $clinicId, array $animalIds, \DateTimeImmutable $nowUtc): ?UpcomingAppointment;
}
