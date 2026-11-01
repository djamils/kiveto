<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\ReschedulePortalAppointment;

use App\Context\ClientPortal\Application\Port\OnlineBookingInterface;
use App\Context\ClientPortal\Application\Service\PortalBookingGuard;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Refusals: `AppointmentNotFoundException` (not one of the account's), then
 * Scheduling's.
 */
#[AsMessageHandler]
final readonly class ReschedulePortalAppointmentHandler
{
    public function __construct(
        private PortalBookingGuard $guard,
        private OnlineBookingInterface $booking,
    ) {
    }

    public function __invoke(ReschedulePortalAppointment $command): string
    {
        $this->guard->assertOwnsAppointment($command->userId, $command->clinicId, $command->appointmentId);

        return $this->booking->reschedule($command->clinicId, $command->appointmentId, $command->practitionerId, $command->startsAt);
    }
}
