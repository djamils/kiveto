<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Adapter\ClientPortal;

use App\Context\ClientPortal\Application\Command\ConfirmBookingRequest\ConfirmBookingRequest;
use App\Context\Scheduling\Application\Port\NewClientConfirmationInterface;
use App\Shared\Application\Bus\CommandBusInterface;

/** ClientPortal's ConfirmBookingRequest, found by the appointment, with its default choices. */
final readonly class CommandBusNewClientConfirmation implements NewClientConfirmationInterface
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function confirm(string $clinicId, string $appointmentId, ?string $animalId, bool $silent): bool
    {
        return true === $this->commandBus->dispatch(ConfirmBookingRequest::forAppointment($clinicId, $appointmentId, $animalId, $silent));
    }
}
