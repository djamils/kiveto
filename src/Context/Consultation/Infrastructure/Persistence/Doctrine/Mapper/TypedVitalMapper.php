<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Consultation\Domain\ValueObject\TypedVitalRecord;
use App\Context\Consultation\Domain\ValueObject\VitalType;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\TypedVitalEntity;
use Symfony\Component\Uid\Uuid;

final class TypedVitalMapper
{
    public function toEntity(
        TypedVitalRecord $vital,
        string $consultationIdBinary,
        int $position,
        ?TypedVitalEntity $entity = null,
    ): TypedVitalEntity {
        $entity ??= new TypedVitalEntity();
        $entity->setId(Uuid::fromString($vital->getId())->toBinary());
        $entity->setConsultationId($consultationIdBinary);
        $entity->setType($vital->getType()->value);
        $entity->setValue($vital->getValue());
        $entity->setRecordedAtUtc($vital->getRecordedAtUtc());
        $entity->setRecordedByUserId(Uuid::fromString($vital->getRecordedByUserId())->toBinary());
        $entity->setPosition($position);

        return $entity;
    }

    public function toDomain(TypedVitalEntity $entity): TypedVitalRecord
    {
        return TypedVitalRecord::reconstitute(
            id: Uuid::fromBinary($entity->getId())->toRfc4122(),
            type: VitalType::from($entity->getType()),
            value: $entity->getValue(),
            recordedAtUtc: $entity->getRecordedAtUtc(),
            recordedByUserId: Uuid::fromBinary($entity->getRecordedByUserId())->toRfc4122(),
        );
    }
}
