<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Clinic\Domain\ClinicGroup;
use App\Context\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicGroupEntity;
use Symfony\Component\Uid\Uuid;

final readonly class ClinicGroupMapper
{
    public function toDomain(ClinicGroupEntity $entity): ClinicGroup
    {
        return ClinicGroup::reconstitute(
            id: ClinicGroupId::fromString($entity->getId()->toString()),
            name: $entity->getName(),
            status: $entity->getStatus(),
            createdAt: $entity->getCreatedAt(),
        );
    }

    public function toEntity(ClinicGroup $clinicGroup): ClinicGroupEntity
    {
        $entity = new ClinicGroupEntity();
        $entity->setId(Uuid::fromString($clinicGroup->id()->toString()));
        $entity->setName($clinicGroup->name());
        $entity->setStatus($clinicGroup->status());
        $entity->setCreatedAt($clinicGroup->createdAt());

        return $entity;
    }
}
