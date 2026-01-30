<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure\Persistence\Doctrine\Mapper;

use App\Scheduling\Domain\ValueObject\AnimalId;
use App\Scheduling\Domain\ValueObject\AppointmentId;
use App\Scheduling\Domain\ValueObject\ClinicId;
use App\Scheduling\Domain\ValueObject\OwnerId;
use App\Scheduling\Domain\ValueObject\UserId;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryId;
use App\Scheduling\Domain\WaitingRoomEntry;
use App\Scheduling\Infrastructure\Persistence\Doctrine\Entity\WaitingRoomEntryEntity;
use Symfony\Component\Uid\Uuid;

final class WaitingRoomEntryMapper
{
    public function toDomain(WaitingRoomEntryEntity $entity): WaitingRoomEntry
    {
        return WaitingRoomEntry::reconstitute(
            id: WaitingRoomEntryId::fromString($entity->getId()->toRfc4122()),
            clinicId: ClinicId::fromString($entity->getClinicId()->toRfc4122()),
            origin: $entity->getOrigin(),
            arrivalMode: $entity->getArrivalMode(),
            linkedAppointmentId: $entity->getLinkedAppointmentId()
                ? AppointmentId::fromString($entity->getLinkedAppointmentId()->toRfc4122())
                : null,
            ownerId: $entity->getOwnerId() ? OwnerId::fromString($entity->getOwnerId()->toRfc4122()) : null,
            animalId: $entity->getAnimalId() ? AnimalId::fromString($entity->getAnimalId()->toRfc4122()) : null,
            foundAnimalDescription: $entity->getFoundAnimalDescription(),
            priority: $entity->getPriority(),
            triageNotes: $entity->getTriageNotes(),
            status: $entity->getStatus(),
            arrivedAtUtc: $entity->getArrivedAtUtc(),
            calledAtUtc: $entity->getCalledAtUtc(),
            serviceStartedAtUtc: $entity->getServiceStartedAtUtc(),
            closedAtUtc: $entity->getClosedAtUtc(),
            calledByUserId: $entity->getCalledByUserId()
                ? UserId::fromString($entity->getCalledByUserId()->toRfc4122())
                : null,
            serviceStartedByUserId: $entity->getServiceStartedByUserId()
                ? UserId::fromString($entity->getServiceStartedByUserId()->toRfc4122())
                : null,
            closedByUserId: $entity->getClosedByUserId()
                ? UserId::fromString($entity->getClosedByUserId()->toRfc4122())
                : null,
        );
    }

    public function toEntity(WaitingRoomEntry $entry): WaitingRoomEntryEntity
    {
        $entity = new WaitingRoomEntryEntity();
        $entity->setId(Uuid::fromString($entry->id()->toString()));
        $entity->setClinicId(Uuid::fromString($entry->clinicId()->toString()));
        $entity->setOrigin($entry->origin());
        $entity->setArrivalMode($entry->arrivalMode());
        $entity->setLinkedAppointmentId(
            $entry->linkedAppointmentId()
                ? Uuid::fromString($entry->linkedAppointmentId()->toString())
                : null
        );
        $entity->setOwnerId($entry->ownerId() ? Uuid::fromString($entry->ownerId()->toString()) : null);
        $entity->setAnimalId($entry->animalId() ? Uuid::fromString($entry->animalId()->toString()) : null);
        $entity->setFoundAnimalDescription($entry->foundAnimalDescription());
        $entity->setPriority($entry->priority());
        $entity->setTriageNotes($entry->triageNotes());
        $entity->setStatus($entry->status());
        $entity->setArrivedAtUtc($entry->arrivedAtUtc());
        $entity->setCalledAtUtc($entry->calledAtUtc());
        $entity->setServiceStartedAtUtc($entry->serviceStartedAtUtc());
        $entity->setClosedAtUtc($entry->closedAtUtc());
        $entity->setCalledByUserId(
            $entry->calledByUserId() ? Uuid::fromString($entry->calledByUserId()->toString()) : null
        );
        $entity->setServiceStartedByUserId(
            $entry->serviceStartedByUserId()
                ? Uuid::fromString($entry->serviceStartedByUserId()->toString())
                : null
        );
        $entity->setClosedByUserId(
            $entry->closedByUserId() ? Uuid::fromString($entry->closedByUserId()->toString()) : null
        );

        return $entity;
    }

    public function updateEntity(WaitingRoomEntry $entry, WaitingRoomEntryEntity $entity): void
    {
        $entity->setArrivalMode($entry->arrivalMode());
        $entity->setOwnerId($entry->ownerId() ? Uuid::fromString($entry->ownerId()->toString()) : null);
        $entity->setAnimalId($entry->animalId() ? Uuid::fromString($entry->animalId()->toString()) : null);
        $entity->setPriority($entry->priority());
        $entity->setTriageNotes($entry->triageNotes());
        $entity->setStatus($entry->status());
        $entity->setCalledAtUtc($entry->calledAtUtc());
        $entity->setServiceStartedAtUtc($entry->serviceStartedAtUtc());
        $entity->setClosedAtUtc($entry->closedAtUtc());
        $entity->setCalledByUserId(
            $entry->calledByUserId() ? Uuid::fromString($entry->calledByUserId()->toString()) : null
        );
        $entity->setServiceStartedByUserId(
            $entry->serviceStartedByUserId()
                ? Uuid::fromString($entry->serviceStartedByUserId()->toString())
                : null
        );
        $entity->setClosedByUserId(
            $entry->closedByUserId() ? Uuid::fromString($entry->closedByUserId()->toString()) : null
        );
    }
}
