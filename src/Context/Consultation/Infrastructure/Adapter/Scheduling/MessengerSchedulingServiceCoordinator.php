<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Adapter\Scheduling;

use App\Context\Consultation\Application\Port\SchedulingServiceCoordinatorInterface;
use App\Context\Consultation\Domain\ValueObject\AppointmentId;
use App\Context\Consultation\Domain\ValueObject\UserId;
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
        // Consultation flow does not block.
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
