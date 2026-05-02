<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
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
            practitionerAssignee: $practitionerAssignee,
            timeSlot: new TimeSlot($entity->getStartsAtUtc(), $entity->getDurationMinutes()),
            status: $entity->getStatus(),
            reason: $entity->getReason(),
            notes: $entity->getNotes(),
            createdAt: $entity->getCreatedAt(),
            serviceStartedAt: $entity->getServiceStartedAt(),
            linkedAdmissionId: $entity->getLinkedAdmissionId()?->toRfc4122(),
            ownerId: $entity->getOwnerId()?->toRfc4122(),
            animalId: $entity->getAnimalId()?->toRfc4122(),
        );
    }

    public function toEntity(Appointment $appointment): AppointmentEntity
    {
        $entity = new AppointmentEntity();
        $entity->setId(Uuid::fromString($appointment->id()->toString()));
        $entity->setClinicId(Uuid::fromString($appointment->clinicId()->toString()));
        $entity->setLinkedAdmissionId(
            null !== $appointment->linkedAdmissionId()
                ? Uuid::fromString($appointment->linkedAdmissionId())
                : null
        );
        $entity->setOwnerId(
            null !== $appointment->ownerId()
                ? Uuid::fromString($appointment->ownerId())
                : null
        );
        $entity->setAnimalId(
            null !== $appointment->animalId()
                ? Uuid::fromString($appointment->animalId())
                : null
        );
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
        $entity->setLinkedAdmissionId(
            null !== $appointment->linkedAdmissionId()
                ? Uuid::fromString($appointment->linkedAdmissionId())
                : null
        );
        $entity->setOwnerId(
            null !== $appointment->ownerId()
                ? Uuid::fromString($appointment->ownerId())
                : null
        );
        $entity->setAnimalId(
            null !== $appointment->animalId()
                ? Uuid::fromString($appointment->animalId())
                : null
        );
        $entity->setUpdatedAt(new \DateTimeImmutable());
    }
}
