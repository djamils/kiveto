<?php

declare(strict_types=1);

namespace App\Context\ClinicalCare\Infrastructure\Adapter\Scheduling;

use App\Context\ClinicalCare\Application\Port\SchedulingServiceCoordinatorInterface;
use App\Context\ClinicalCare\Domain\ValueObject\AppointmentId;
use App\Context\ClinicalCare\Domain\ValueObject\UserId;
use App\Context\ClinicalCare\Domain\ValueObject\WaitingRoomEntryId;
use App\Context\Scheduling\Application\Command\StartServiceForWaitingRoomEntry\StartServiceForWaitingRoomEntry;
use App\Shared\Application\Bus\CommandBusInterface;

final readonly class MessengerSchedulingServiceCoordinator implements SchedulingServiceCoordinatorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    public function ensureAppointmentInService(
        AppointmentId $appointmentId,
        UserId $triggeredByUserId,
    ): void {
        // TODO: Scheduling BC does not yet expose a StartServiceForAppointment command;
        // when it does, dispatch it here. Until then this is a no-op so the calling
        // ClinicalCare flow does not block.
    }

    public function ensureWaitingRoomEntryInService(
        WaitingRoomEntryId $entryId,
        UserId $triggeredByUserId,
    ): void {
        try {
            $this->commandBus->dispatch(
                new StartServiceForWaitingRoomEntry(
                    waitingRoomEntryId: $entryId->toString(),
                    serviceStartedByUserId: $triggeredByUserId->toString(),
                )
            );
        } catch (\Exception) {
            // Ignore
        }
    }

    public function completeAppointment(
        AppointmentId $appointmentId,
        UserId $triggeredByUserId,
    ): void {
        try {
            $this->commandBus->dispatch(
                new \App\Context\Scheduling\Application\Command\CompleteAppointment\CompleteAppointment(
                    appointmentId: $appointmentId->toString(),
                )
            );
        } catch (\Exception) {
            // Ignore
        }
    }
}
