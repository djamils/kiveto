<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Infrastructure\Persistence\Doctrine;

use App\Context\Regulatory\Domain\ICADLookup;
use App\Context\Regulatory\Domain\ValueObject\ICADLookupId;
use App\Context\Regulatory\Infrastructure\Persistence\Doctrine\Entity\ICADLookupEntity;
use Symfony\Component\Uid\Uuid;

final readonly class ICADLookupMapper
{
    public function toDomain(ICADLookupEntity $entity): ICADLookup
    {
        return ICADLookup::reconstituteFromPersistence(
            id: ICADLookupId::fromString($entity->getId()->toString()),
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

    public function toEntity(ICADLookup $lookup): ICADLookupEntity
    {
        $entity = new ICADLookupEntity();

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
