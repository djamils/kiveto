<?php

declare(strict_types=1);

namespace App\ClinicalCare\Infrastructure\Adapter\Scheduling;

use App\ClinicalCare\Application\Port\SchedulingServiceCoordinatorInterface;
use App\ClinicalCare\Domain\ValueObject\AppointmentId;
use App\ClinicalCare\Domain\ValueObject\UserId;
use App\ClinicalCare\Domain\ValueObject\WaitingRoomEntryId;
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
        try {
            $this->commandBus->dispatch(
                new \App\Scheduling\Application\Command\StartServiceForAppointment\StartServiceForAppointment(
                    appointmentId: $appointmentId->toString(),
                    serviceStartedByUserId: $triggeredByUserId->toString(),
                )
            );
        } catch (\Exception) {
            // Already in service or completed = OK, ignore
        }
    }

    public function ensureWaitingRoomEntryInService(
        WaitingRoomEntryId $entryId,
        UserId $triggeredByUserId,
    ): void {
        try {
            $this->commandBus->dispatch(
                new \App\Scheduling\Application\Command\StartServiceForWaitingRoomEntry\StartServiceForWaitingRoomEntry(
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
                new \App\Scheduling\Application\Command\CompleteAppointment\CompleteAppointment(
                    appointmentId: $appointmentId->toString(),
                    completedByUserId: $triggeredByUserId->toString(),
                )
            );
        } catch (\Exception) {
            // Ignore
        }
    }
}
