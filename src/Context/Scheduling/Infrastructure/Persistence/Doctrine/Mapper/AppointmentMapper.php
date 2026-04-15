<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\ValueObject\AnimalId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\OwnerId;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\AppointmentEntity;
use Symfony\Component\Uid\Uuid;

final class AppointmentMapper
{
    public function toDomain(AppointmentEntity $entity): Appointment
    {
        $practitionerAssignee = new PractitionerAssignee(
            UserId::fromString($entity->getPractitionerUserId()->toRfc4122())
        );

        return Appointment::reconstitute(
            id: AppointmentId::fromString($entity->getId()->toRfc4122()),
            clinicId: ClinicId::fromString($entity->getClinicId()->toRfc4122()),
            ownerId: $entity->getOwnerId() ? OwnerId::fromString($entity->getOwnerId()->toRfc4122()) : null,
            animalId: $entity->getAnimalId() ? AnimalId::fromString($entity->getAnimalId()->toRfc4122()) : null,
            practitionerAssignee: $practitionerAssignee,
            timeSlot: new TimeSlot($entity->getStartsAtUtc(), $entity->getDurationMinutes()),
            status: $entity->getStatus(),
            reason: $entity->getReason(),
            notes: $entity->getNotes(),
            createdAt: $entity->getCreatedAt(),
            serviceStartedAt: $entity->getServiceStartedAt(),
        );
    }

    public function toEntity(Appointment $appointment): AppointmentEntity
    {
        $entity = new AppointmentEntity();
        $entity->setId(Uuid::fromString($appointment->id()->toString()));
        $entity->setClinicId(Uuid::fromString($appointment->clinicId()->toString()));
        $entity->setOwnerId($appointment->ownerId() ? Uuid::fromString($appointment->ownerId()->toString()) : null);
        $entity->setAnimalId($appointment->animalId() ? Uuid::fromString($appointment->animalId()->toString()) : null);
        $entity->setPractitionerUserId(Uuid::fromString($appointment->practitionerAssignee()->userId()->toString()));
        $entity->setStartsAtUtc($appointment->timeSlot()->startsAtUtc());
        $entity->setDurationMinutes($appointment->timeSlot()->durationMinutes());
        $entity->setStatus($appointment->status());
        $entity->setReason($appointment->reason());
        $entity->setNotes($appointment->notes());
        $entity->setServiceStartedAt($appointment->serviceStartedAt());
        $entity->setCreatedAt($appointment->createdAt());
        $entity->setUpdatedAt(new \DateTimeImmutable());

        return $entity;
    }

    public function updateEntity(Appointment $appointment, AppointmentEntity $entity): void
    {
        $entity->setPractitionerUserId(Uuid::fromString($appointment->practitionerAssignee()->userId()->toString()));
        $entity->setStartsAtUtc($appointment->timeSlot()->startsAtUtc());
        $entity->setDurationMinutes($appointment->timeSlot()->durationMinutes());
        $entity->setStatus($appointment->status());
        $entity->setServiceStartedAt($appointment->serviceStartedAt());
        $entity->setUpdatedAt(new \DateTimeImmutable());
    }
}
