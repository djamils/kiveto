<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Mapper;

use App\System\PharmaceuticalRegistry\Domain\Entity\Composition;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ActiveSubstanceId;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\ActiveSubstanceEntity;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\AuthorizationEntity;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\CompositionEntity;
use Symfony\Component\Uid\Uuid;

final readonly class CompositionMapper
{
    public function toDomain(CompositionEntity $entity): Composition
    {
        return Composition::of(
            activeSubstanceId: ActiveSubstanceId::fromString($entity->getActiveSubstance()->getId()->toString()),
            quantityValue: $entity->getQuantityValue(),
            quantityUnitLabel: $entity->getQuantityUnitLabel(),
            quantityUnitCode: $entity->getQuantityUnitCode(),
            isExcipient: $entity->isExcipient(),
        );
    }

    public function toEntity(
        Composition $composition,
        AuthorizationEntity $authorization,
        ActiveSubstanceEntity $activeSubstance,
    ): CompositionEntity {
        $entity = new CompositionEntity();
        $entity->setId(Uuid::v7());
        $entity->setAuthorization($authorization);
        $entity->setActiveSubstance($activeSubstance);
        $entity->setQuantityValue($composition->quantityValue());
        $entity->setQuantityUnitLabel($composition->quantityUnitLabel());
        $entity->setQuantityUnitCode($composition->quantityUnitCode());
        $entity->setIsExcipient($composition->isExcipient());

        return $entity;
    }
}
