<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Mapper;

use App\System\PharmaceuticalRegistry\Domain\ActiveSubstance;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ActiveSubstanceId;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\ActiveSubstanceEntity;
use Symfony\Component\Uid\Uuid;

final readonly class ActiveSubstanceMapper
{
    public function toDomain(ActiveSubstanceEntity $entity): ActiveSubstance
    {
        return ActiveSubstance::reconstitute(
            id: ActiveSubstanceId::fromString($entity->getId()->toString()),
            label: $entity->getLabel(),
            labelNormalized: $entity->getLabelNormalized(),
            innCode: $entity->getInnCode(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }

    public function toEntity(ActiveSubstance $substance): ActiveSubstanceEntity
    {
        $entity = new ActiveSubstanceEntity();
        $entity->setId(Uuid::fromString($substance->id()->toString()));
        $entity->setLabel($substance->label());
        $entity->setLabelNormalized($substance->labelNormalized());
        $entity->setInnCode($substance->innCode());
        $entity->setCreatedAt($substance->createdAt());
        $entity->setUpdatedAt($substance->updatedAt());

        return $entity;
    }
}
