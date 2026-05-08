<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Infrastructure\Persistence\Doctrine;

use App\Context\Regulatory\Domain\AuthorityNotification;
use App\Context\Regulatory\Domain\ValueObject\AuthorityNotificationId;
use App\Context\Regulatory\Infrastructure\Persistence\Doctrine\Entity\AuthorityNotificationEntity;
use Symfony\Component\Uid\Uuid;

final readonly class AuthorityNotificationMapper
{
    public function toDomain(AuthorityNotificationEntity $entity): AuthorityNotification
    {
        return AuthorityNotification::reconstituteFromPersistence(
            id: AuthorityNotificationId::fromString($entity->getId()->toString()),
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

    public function toEntity(AuthorityNotification $notification): AuthorityNotificationEntity
    {
        $entity = new AuthorityNotificationEntity();

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
