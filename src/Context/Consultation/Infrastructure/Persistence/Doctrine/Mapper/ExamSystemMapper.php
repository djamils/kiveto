<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Consultation\Domain\ValueObject\BodySystem;
use App\Context\Consultation\Domain\ValueObject\ExamStatus;
use App\Context\Consultation\Domain\ValueObject\ExamSystemRecord;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\ExamSystemEntity;
use Symfony\Component\Uid\Uuid;

final class ExamSystemMapper
{
    public function toEntity(
        ExamSystemRecord $exam,
        string $consultationIdBinary,
        int $position,
        ?ExamSystemEntity $entity = null,
    ): ExamSystemEntity {
        $entity ??= new ExamSystemEntity();
        $entity->setId(Uuid::fromString($exam->getId())->toBinary());
        $entity->setConsultationId($consultationIdBinary);
        $entity->setSystem($exam->getSystem()->value);
        $entity->setStatus($exam->getStatus()->value);
        $entity->setNotes($exam->getNotes());
        $entity->setStructuredData($exam->getStructuredData());
        $entity->setRecordedAtUtc($exam->getRecordedAtUtc());
        $entity->setRecordedByUserId(Uuid::fromString($exam->getRecordedByUserId())->toBinary());
        $entity->setPosition($position);

        return $entity;
    }

    public function toDomain(ExamSystemEntity $entity): ExamSystemRecord
    {
        return ExamSystemRecord::reconstitute(
            id: Uuid::fromBinary($entity->getId())->toRfc4122(),
            system: BodySystem::from($entity->getSystem()),
            status: ExamStatus::from($entity->getStatus()),
            notes: $entity->getNotes(),
            structuredData: $entity->getStructuredData(),
            recordedAtUtc: $entity->getRecordedAtUtc(),
            recordedByUserId: Uuid::fromBinary($entity->getRecordedByUserId())->toRfc4122(),
        );
    }
}
