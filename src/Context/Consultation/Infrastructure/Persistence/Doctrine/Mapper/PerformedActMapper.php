<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Consultation\Domain\ValueObject\PerformedActRecord;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\PerformedActEntity;
use Symfony\Component\Uid\Uuid;

final class PerformedActMapper
{
    public function toEntity(PerformedActRecord $act, string $consultationIdBinary): PerformedActEntity
    {
        $entity = new PerformedActEntity();
        $entity->setId(Uuid::fromString($act->getId())->toBinary());
        $entity->setConsultationId($consultationIdBinary);
        $entity->setLabel($act->getLabel());
        $entity->setQuantity((string) $act->getQuantity());
        $entity->setPerformedAtUtc($act->getPerformedAtUtc());
        $entity->setCreatedAtUtc($act->getCreatedAtUtc());
        $entity->setCreatedByUserId(Uuid::fromString($act->getCreatedByUserId())->toBinary());

        return $entity;
    }

    public function toDomain(PerformedActEntity $entity): PerformedActRecord
    {
        return PerformedActRecord::reconstitute(
            id: Uuid::fromBinary($entity->getId())->toRfc4122(),
            label: $entity->getLabel(),
            quantity: (float) $entity->getQuantity(),
            performedAtUtc: $entity->getPerformedAtUtc(),
            createdAtUtc: $entity->getCreatedAtUtc(),
            createdByUserId: Uuid::fromBinary($entity->getCreatedByUserId())->toRfc4122(),
        );
    }
}
