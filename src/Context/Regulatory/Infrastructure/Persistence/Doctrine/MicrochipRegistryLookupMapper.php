<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Infrastructure\Persistence\Doctrine;

use App\Context\Regulatory\Domain\MicrochipRegistryLookup;
use App\Context\Regulatory\Domain\ValueObject\MicrochipRegistryLookupId;
use App\Context\Regulatory\Infrastructure\Persistence\Doctrine\Entity\MicrochipRegistryLookupEntity;
use Symfony\Component\Uid\Uuid;

final readonly class MicrochipRegistryLookupMapper
{
    public function toDomain(MicrochipRegistryLookupEntity $entity): MicrochipRegistryLookup
    {
        return MicrochipRegistryLookup::reconstituteFromPersistence(
            id: MicrochipRegistryLookupId::fromString($entity->getId()->toString()),
            chipNumber: $entity->getChipNumber(),
            clinicId: $entity->getClinicId()->toString(),
            status: $entity->getStatus(),
            icadAnimalData: $entity->getIcadAnimalData(),
            errorMessage: $entity->getErrorMessage(),
            version: $entity->getVersion(),
            initiatedAt: $entity->getInitiatedAt(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }

    public function toEntity(MicrochipRegistryLookup $lookup): MicrochipRegistryLookupEntity
    {
        $entity = new MicrochipRegistryLookupEntity();

        $entity->setId(Uuid::fromString($lookup->id()->value()));
        $entity->setChipNumber($lookup->chipNumber());
        $entity->setClinicId(Uuid::fromString($lookup->clinicId()));
        $entity->setStatus($lookup->status());
        $entity->setIcadAnimalData($lookup->icadAnimalData());
        $entity->setErrorMessage($lookup->errorMessage());
        $entity->setVersion($lookup->version());
        $entity->setInitiatedAt($lookup->initiatedAt());
        $entity->setCreatedAt($lookup->createdAt());
        $entity->setUpdatedAt($lookup->updatedAt());

        return $entity;
    }
}
