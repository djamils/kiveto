<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Consultation\Domain\ValueObject\PlanActionKind;
use App\Context\Consultation\Domain\ValueObject\PlanActionRecord;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\PlanActionEntity;
use Symfony\Component\Uid\Uuid;

final class PlanActionMapper
{
    public function toEntity(
        PlanActionRecord $action,
        string $consultationIdBinary,
        int $position,
        ?PlanActionEntity $entity = null,
    ): PlanActionEntity {
        $entity ??= new PlanActionEntity();
        $entity->setId(Uuid::fromString($action->getId())->toBinary());
        $entity->setConsultationId($consultationIdBinary);
        $entity->setKind($action->getKind()->value);
        $entity->setDescription($action->getDescription());
        $entity->setCatalogCode($action->getCatalogCode());
        $entity->setPosology($action->getPosology());
        $entity->setDurationDays($action->getDurationDays());
        $entity->setFollowUpDays($action->getFollowUpDays());
        $entity->setQuantity((string) $action->getQuantity());
        $entity->setUnitPriceMinorUnits($action->getUnitPriceMinorUnits());
        $entity->setCurrency($action->getCurrency());
        $entity->setTaxCategoryCode($action->getTaxCategoryCode());
        $entity->setCreatedAtUtc($action->getCreatedAtUtc());
        $entity->setCreatedByUserId(Uuid::fromString($action->getCreatedByUserId())->toBinary());
        $entity->setPosition($position);

        return $entity;
    }

    public function toDomain(PlanActionEntity $entity): PlanActionRecord
    {
        return PlanActionRecord::reconstitute(
            id: Uuid::fromBinary($entity->getId())->toRfc4122(),
            kind: PlanActionKind::from($entity->getKind()),
            description: $entity->getDescription(),
            catalogCode: $entity->getCatalogCode(),
            posology: $entity->getPosology(),
            durationDays: $entity->getDurationDays(),
            followUpDays: $entity->getFollowUpDays(),
            quantity: (float) $entity->getQuantity(),
            unitPriceMinorUnits: $entity->getUnitPriceMinorUnits(),
            currency: $entity->getCurrency(),
            taxCategoryCode: $entity->getTaxCategoryCode(),
            createdAtUtc: $entity->getCreatedAtUtc(),
            createdByUserId: Uuid::fromBinary($entity->getCreatedByUserId())->toRfc4122(),
        );
    }
}
