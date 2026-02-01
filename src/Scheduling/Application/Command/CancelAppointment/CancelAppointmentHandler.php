<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Command\CancelAppointment;

use App\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Scheduling\Domain\Repository\WaitingRoomEntryRepositoryInterface;
use App\Scheduling\Domain\ValueObject\AppointmentId;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryStatus;
use App\Scheduling\Infrastructure\Persistence\Doctrine\Entity\WaitingRoomEntryEntity;
use App\Scheduling\Infrastructure\Persistence\Doctrine\Mapper\WaitingRoomEntryMapper;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class CancelAppointmentHandler
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
        private WaitingRoomEntryRepositoryInterface $waitingRoomEntryRepository,
        private WaitingRoomEntryMapper $waitingRoomEntryMapper,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CancelAppointment $command): void
    {
        $appointmentId = AppointmentId::fromString($command->appointmentId);

        // Load and cancel appointment
        $appointment = $this->appointmentRepository->findById($appointmentId);
        if (null === $appointment) {
            throw new \InvalidArgumentException(\sprintf(
                'Appointment with ID "%s" does not exist.',
                $command->appointmentId
            ));
        }

        $appointment->cancel();
        $this->appointmentRepository->save($appointment);

        // Policy: Close active waiting room entry if exists
        $this->closeActiveWaitingRoomEntry($appointmentId);
    }

    private function closeActiveWaitingRoomEntry(AppointmentId $appointmentId): void
    {
        $repository = $this->entityManager->getRepository(WaitingRoomEntryEntity::class);

        $entity = $repository->findOneBy([
            'linkedAppointmentId' => Uuid::fromString($appointmentId->toString()),
            'status'              => [
                WaitingRoomEntryStatus::WAITING->value,
                WaitingRoomEntryStatus::CALLED->value,
                WaitingRoomEntryStatus::IN_SERVICE->value,
            ],
        ]);

        if (null !== $entity) {
            $entry = $this->waitingRoomEntryMapper->toDomain($entity);
            $entry->close($this->clock->now(), null);
            $this->waitingRoomEntryRepository->save($entry);
        }
    }
}
