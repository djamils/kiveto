<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Infrastructure\Persistence\Doctrine;

use App\Context\Regulatory\Domain\MairieNotification;
use App\Context\Regulatory\Domain\ValueObject\MairieNotificationId;
use App\Context\Regulatory\Infrastructure\Persistence\Doctrine\Entity\MairieNotificationEntity;
use Symfony\Component\Uid\Uuid;

final readonly class MairieNotificationMapper
{
    public function toDomain(MairieNotificationEntity $entity): MairieNotification
    {
        return MairieNotification::reconstituteFromPersistence(
            id: MairieNotificationId::fromString($entity->getId()->toString()),
            admissionId: $entity->getAdmissionId()->toString(),
            patientId: $entity->getPatientId()->toString(),
            clinicId: $entity->getClinicId()->toString(),
            status: $entity->getStatus(),
            deadline: $entity->getDeadline(),
            version: $entity->getVersion(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }

    public function toEntity(MairieNotification $notification): MairieNotificationEntity
    {
        $entity = new MairieNotificationEntity();

        $entity->setId(Uuid::fromString($notification->id()->value()));
        $entity->setAdmissionId(Uuid::fromString($notification->admissionId()));
        $entity->setPatientId(Uuid::fromString($notification->patientId()));
        $entity->setClinicId(Uuid::fromString($notification->clinicId()));
        $entity->setStatus($notification->status());
        $entity->setDeadline($notification->deadline());
        $entity->setVersion($notification->version());
        $entity->setCreatedAt($notification->createdAt());
        $entity->setUpdatedAt($notification->updatedAt());

        return $entity;
    }
}
