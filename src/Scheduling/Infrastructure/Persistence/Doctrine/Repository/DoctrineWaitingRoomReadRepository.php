<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure\Persistence\Doctrine\Repository;

use App\Scheduling\Application\Port\WaitingRoomReadRepositoryInterface;
use App\Scheduling\Application\Query\GetWaitingRoomEntryDetails\WaitingRoomEntryDetailsDTO;
use App\Scheduling\Domain\ValueObject\AppointmentId;
use App\Scheduling\Domain\ValueObject\ClinicId;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryId;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryStatus;
use App\Scheduling\Infrastructure\Persistence\Doctrine\Entity\WaitingRoomEntryEntity;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineWaitingRoomReadRepository implements WaitingRoomReadRepositoryInterface
{
    public function __construct(
        private Connection $connection,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function hasActiveEntryForAppointment(ClinicId $clinicId, AppointmentId $appointmentId): bool
    {
        $sql = <<<'SQL'
            SELECT COUNT(*) as cnt
            FROM scheduling__waiting_room_entries
            WHERE clinic_id = :clinicId
              AND linked_appointment_id = :appointmentId
              AND status IN (:activeStatuses)
        SQL;

        $result = $this->connection->fetchAssociative($sql, [
            'clinicId'       => $clinicId->toString(),
            'appointmentId'  => $appointmentId->toString(),
            'activeStatuses' => [
                WaitingRoomEntryStatus::WAITING->value,
                WaitingRoomEntryStatus::CALLED->value,
                WaitingRoomEntryStatus::IN_SERVICE->value,
            ],
        ], [
            'activeStatuses' => ArrayParameterType::STRING,
        ]);

        return ($result['cnt'] ?? 0) > 0;
    }

    public function getActiveStatuses(): array
    {
        return [
            WaitingRoomEntryStatus::WAITING,
            WaitingRoomEntryStatus::CALLED,
            WaitingRoomEntryStatus::IN_SERVICE,
        ];
    }

    public function findById(WaitingRoomEntryId $waitingRoomEntryId): WaitingRoomEntryDetailsDTO
    {
        $repository = $this->entityManager->getRepository(WaitingRoomEntryEntity::class);

        // Convert string UUID to Symfony Uuid object for Doctrine
        $uuid = \Symfony\Component\Uid\Uuid::fromString($waitingRoomEntryId->toString());

        $entity = $repository->find($uuid);

        if (null === $entity) {
            throw new \DomainException(\sprintf(
                'Waiting room entry "%s" not found.',
                $waitingRoomEntryId->toString()
            ));
        }

        return new WaitingRoomEntryDetailsDTO(
            waitingRoomEntryId: $entity->getId()->toString(),
            clinicId: $entity->getClinicId()->toString(),
            status: $entity->getStatus()->value,
            origin: $entity->getOrigin()->value,
            arrivalMode: $entity->getArrivalMode()->value,
            linkedAppointmentId: $entity->getLinkedAppointmentId()?->toString(),
            ownerId: $entity->getOwnerId()?->toString(),
            animalId: $entity->getAnimalId()?->toString(),
            triageNotes: $entity->getTriageNotes(),
            arrivedAtUtc: $entity->getArrivedAtUtc()->format('c'),
            calledAtUtc: $entity->getCalledAtUtc()?->format('c'),
            serviceStartedAtUtc: $entity->getServiceStartedAtUtc()?->format('c'),
            closedAtUtc: $entity->getClosedAtUtc()?->format('c'),
        );
    }
}
