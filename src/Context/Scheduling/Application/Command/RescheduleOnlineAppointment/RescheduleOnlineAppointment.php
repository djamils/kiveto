<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\RescheduleOnlineAppointment;

use App\Shared\Application\Bus\CommandInterface;

/**
 * A client moves their online booking to another slot offered for its
 * motif, possibly with another practitioner of the motif. Answers the
 * appointment id.
 */
final readonly class RescheduleOnlineAppointment implements CommandInterface
{
    /**
     * @param \DateTimeImmutable $startsAt any time zone; stored in UTC
     */
    public function __construct(
        public string $clinicId,
        public string $appointmentId,
        public string $practitionerUserId,
        public \DateTimeImmutable $startsAt,
    ) {
    }
}
