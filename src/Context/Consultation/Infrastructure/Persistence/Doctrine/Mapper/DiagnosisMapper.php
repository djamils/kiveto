<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Consultation\Domain\ValueObject\DiagnosisCertainty;
use App\Context\Consultation\Domain\ValueObject\DiagnosisRecord;
use App\Context\Consultation\Domain\ValueObject\DiagnosisSource;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\DiagnosisEntity;
use Symfony\Component\Uid\Uuid;

final class DiagnosisMapper
{
    public function toEntity(
        DiagnosisRecord $diagnosis,
        string $consultationIdBinary,
        int $position,
        ?DiagnosisEntity $entity = null,
    ): DiagnosisEntity {
        $entity ??= new DiagnosisEntity();
        $entity->setId(Uuid::fromString($diagnosis->getId())->toBinary());
        $entity->setConsultationId($consultationIdBinary);
        $entity->setCode($diagnosis->getCode());
        $entity->setLabel($diagnosis->getLabel());
        $entity->setCertainty($diagnosis->getCertainty()->value);
        $entity->setNote($diagnosis->getNote());
        $entity->setIsPrimary($diagnosis->isPrimary());
        $entity->setSource($diagnosis->getSource()->value);
        $entity->setCreatedAtUtc($diagnosis->getCreatedAtUtc());
        $entity->setCreatedByUserId(Uuid::fromString($diagnosis->getCreatedByUserId())->toBinary());
        $entity->setPosition($position);

        return $entity;
    }

    public function toDomain(DiagnosisEntity $entity): DiagnosisRecord
    {
        return DiagnosisRecord::reconstitute(
            id: Uuid::fromBinary($entity->getId())->toRfc4122(),
            code: $entity->getCode(),
            label: $entity->getLabel(),
            certainty: DiagnosisCertainty::from($entity->getCertainty()),
            note: $entity->getNote(),
            isPrimary: $entity->isPrimary(),
            source: DiagnosisSource::from($entity->getSource()),
            createdAtUtc: $entity->getCreatedAtUtc(),
            createdByUserId: Uuid::fromBinary($entity->getCreatedByUserId())->toRfc4122(),
        );
    }
}
