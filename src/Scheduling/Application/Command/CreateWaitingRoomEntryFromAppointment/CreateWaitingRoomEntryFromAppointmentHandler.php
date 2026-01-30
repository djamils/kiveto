<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Command\CreateWaitingRoomEntryFromAppointment;

use App\Scheduling\Application\Exception\WaitingRoomEntryAlreadyExistsException;
use App\Scheduling\Application\Port\WaitingRoomReadRepositoryInterface;
use App\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Scheduling\Domain\Repository\WaitingRoomEntryRepositoryInterface;
use App\Scheduling\Domain\ValueObject\AppointmentId;
use App\Scheduling\Domain\ValueObject\WaitingRoomArrivalMode;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryId;
use App\Scheduling\Domain\WaitingRoomEntry;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateWaitingRoomEntryFromAppointmentHandler
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
        private WaitingRoomEntryRepositoryInterface $waitingRoomEntryRepository,
        private WaitingRoomReadRepositoryInterface $waitingRoomReadRepository,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateWaitingRoomEntryFromAppointment $command): string
    {
        $appointmentId = AppointmentId::fromString($command->appointmentId);

        // Validate appointment exists
        $appointment = $this->appointmentRepository->findById($appointmentId);
        if (null === $appointment) {
            throw new \InvalidArgumentException(\sprintf(
                'Appointment with ID "%s" does not exist.',
                $command->appointmentId
            ));
        }

        // Ensure no active entry for this appointment
        if ($this->waitingRoomReadRepository->hasActiveEntryForAppointment(
            $appointment->clinicId(),
            $appointmentId
        )) {
            throw new WaitingRoomEntryAlreadyExistsException(\sprintf(
                'An active waiting room entry already exists for appointment "%s".',
                $command->appointmentId
            ));
        }

        $entryId     = WaitingRoomEntryId::fromString($this->uuidGenerator->generate());
        $arrivalMode = WaitingRoomArrivalMode::from($command->arrivalMode);

        $entry = WaitingRoomEntry::createFromAppointment(
            id: $entryId,
            clinicId: $appointment->clinicId(),
            linkedAppointmentId: $appointmentId,
            ownerId: $appointment->ownerId(),
            animalId: $appointment->animalId(),
            arrivalMode: $arrivalMode,
            priority: $command->priority,
            arrivedAtUtc: $this->clock->now(),
        );

        $this->waitingRoomEntryRepository->save($entry);

        return $entryId->toString();
    }
}
