<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Scheduling;

use App\Context\ClientPortal\Application\Port\OnlineBookingInterface;
use App\Context\Scheduling\Application\Command\BookOnlineAppointment\BookOnlineAppointment;
use App\Context\Scheduling\Application\Command\CancelOnlineAppointment\CancelOnlineAppointment;
use App\Context\Scheduling\Application\Command\RescheduleOnlineAppointment\RescheduleOnlineAppointment;
use App\Shared\Application\Bus\CommandBusInterface;

/** Scheduling's online booking commands; their refusals pass through. */
final readonly class CommandBusOnlineBooking implements OnlineBookingInterface
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function book(
        string $clinicId,
        string $motifId,
        string $practitionerId,
        \DateTimeImmutable $startsAt,
        string $ownerId,
        string $animalId,
        ?string $createdPetId = null,
    ): string {
        return self::id($this->commandBus->dispatch(new BookOnlineAppointment($clinicId, $motifId, $practitionerId, $startsAt, $ownerId, $animalId, $createdPetId)));
    }

    public function reschedule(string $clinicId, string $appointmentId, string $practitionerId, \DateTimeImmutable $startsAt): string
    {
        return self::id($this->commandBus->dispatch(new RescheduleOnlineAppointment($clinicId, $appointmentId, $practitionerId, $startsAt)));
    }

    public function cancel(string $clinicId, string $appointmentId, ?string $reason): string
    {
        return self::id($this->commandBus->dispatch(new CancelOnlineAppointment($clinicId, $appointmentId, $reason)));
    }

    private static function id(mixed $id): string
    {
        \assert(\is_string($id));

        return $id;
    }
}
