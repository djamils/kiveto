<?php

declare(strict_types=1);

namespace App\Context\Animal\Infrastructure\Persistence\Doctrine;

use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\WeightRecordId;
use App\Context\Animal\Domain\WeightRecord;
use App\Context\Animal\Infrastructure\Persistence\Doctrine\Entity\WeightRecordEntity;
use Symfony\Component\Uid\Uuid;

final readonly class WeightRecordMapper
{
    public function toDomain(WeightRecordEntity $entity): WeightRecord
    {
        return WeightRecord::reconstitute(
            id: WeightRecordId::fromString($entity->id->toString()),
            clinicId: ClinicId::fromString($entity->clinicId->toString()),
            animalId: AnimalId::fromString($entity->animalId->toString()),
            weightGrams: $entity->weightGrams,
            bodyConditionScore: $entity->bodyConditionScore,
            measuredOn: $entity->measuredOn,
            authorName: $entity->authorName,
            recordedAt: $entity->recordedAt,
        );
    }

    public function toEntity(WeightRecord $record): WeightRecordEntity
    {
        return new WeightRecordEntity(
            id: Uuid::fromString($record->id()->toString()),
            clinicId: Uuid::fromString($record->clinicId()->toString()),
            animalId: Uuid::fromString($record->animalId()->toString()),
            weightGrams: $record->weightGrams(),
            bodyConditionScore: $record->bodyConditionScore(),
            measuredOn: $record->measuredOn(),
            authorName: $record->authorName(),
            recordedAt: $record->recordedAt(),
        );
    }
}
