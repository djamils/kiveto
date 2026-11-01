<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\ReschedulePortalAppointment;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The owner moves one of their online bookings. Answers the appointment id.
 */
final readonly class ReschedulePortalAppointment implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
        public string $appointmentId,
        public string $practitionerId,
        public \DateTimeImmutable $startsAt,
    ) {
    }
}
