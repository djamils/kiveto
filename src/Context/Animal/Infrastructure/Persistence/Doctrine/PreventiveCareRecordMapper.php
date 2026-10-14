<?php

declare(strict_types=1);

namespace App\Context\Animal\Infrastructure\Persistence\Doctrine;

use App\Context\Animal\Domain\PreventiveCareRecord;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\PreventiveCareRecordId;
use App\Context\Animal\Infrastructure\Persistence\Doctrine\Entity\PreventiveCareRecordEntity;
use Symfony\Component\Uid\Uuid;

final readonly class PreventiveCareRecordMapper
{
    public function toDomain(PreventiveCareRecordEntity $entity): PreventiveCareRecord
    {
        return PreventiveCareRecord::reconstitute(
            id: PreventiveCareRecordId::fromString($entity->id->toString()),
            clinicId: ClinicId::fromString($entity->clinicId->toString()),
            animalId: AnimalId::fromString($entity->animalId->toString()),
            kind: $entity->kind,
            label: $entity->label,
            administeredOn: $entity->administeredOn,
            batchNumber: $entity->batchNumber,
            nextDueOn: $entity->nextDueOn,
            authorName: $entity->authorName,
            recordedAt: $entity->recordedAt,
        );
    }

    public function toEntity(PreventiveCareRecord $record): PreventiveCareRecordEntity
    {
        return new PreventiveCareRecordEntity(
            id: Uuid::fromString($record->id()->toString()),
            clinicId: Uuid::fromString($record->clinicId()->toString()),
            animalId: Uuid::fromString($record->animalId()->toString()),
            kind: $record->kind(),
            label: $record->label(),
            administeredOn: $record->administeredOn(),
            batchNumber: $record->batchNumber(),
            nextDueOn: $record->nextDueOn(),
            authorName: $record->authorName(),
            recordedAt: $record->recordedAt(),
        );
    }
}
