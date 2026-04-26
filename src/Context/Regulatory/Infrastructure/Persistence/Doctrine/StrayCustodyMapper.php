<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Infrastructure\Persistence\Doctrine;

use App\Context\Regulatory\Domain\StrayCustody;
use App\Context\Regulatory\Domain\ValueObject\StrayCustodyId;
use App\Context\Regulatory\Infrastructure\Persistence\Doctrine\Entity\StrayCustodyEntity;
use Symfony\Component\Uid\Uuid;

final readonly class StrayCustodyMapper
{
    public function toDomain(StrayCustodyEntity $entity): StrayCustody
    {
        return StrayCustody::reconstituteFromPersistence(
            id: StrayCustodyId::fromString($entity->getId()->toString()),
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

    public function toEntity(StrayCustody $custody): StrayCustodyEntity
    {
        $entity = new StrayCustodyEntity();

        $entity->setId(Uuid::fromString($custody->id()->value()));
        $entity->setAdmissionId(Uuid::fromString($custody->admissionId()));
        $entity->setPatientId(Uuid::fromString($custody->patientId()));
        $entity->setClinicId(Uuid::fromString($custody->clinicId()));
        $entity->setStatus($custody->status());
        $entity->setDeadline($custody->deadline());
        $entity->setVersion($custody->version());
        $entity->setCreatedAt($custody->createdAt());
        $entity->setUpdatedAt($custody->updatedAt());

        return $entity;
    }
}
