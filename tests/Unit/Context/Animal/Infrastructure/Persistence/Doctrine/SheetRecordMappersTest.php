<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Infrastructure\Persistence\Doctrine;

use App\Context\Animal\Domain\PreventiveCareRecord;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\PreventiveCareKind;
use App\Context\Animal\Domain\ValueObject\PreventiveCareRecordId;
use App\Context\Animal\Domain\ValueObject\WeightRecordId;
use App\Context\Animal\Domain\WeightRecord;
use App\Context\Animal\Infrastructure\Persistence\Doctrine\PreventiveCareRecordMapper;
use App\Context\Animal\Infrastructure\Persistence\Doctrine\WeightRecordMapper;
use PHPUnit\Framework\TestCase;

final class SheetRecordMappersTest extends TestCase
{
    private const string RECORD_ID = '0192c000-0000-7000-8000-000000000001';
    private const string ANIMAL_ID = '0192b000-0000-7000-8000-000000000001';
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';

    public function testAWeightRecordSurvivesARoundTrip(): void
    {
        $mapper     = new WeightRecordMapper();
        $recordedAt = new \DateTimeImmutable('2026-08-22 10:30:00');
        $record     = WeightRecord::reconstitute(
            WeightRecordId::fromString(self::RECORD_ID),
            ClinicId::fromString(self::CLINIC_ID),
            AnimalId::fromString(self::ANIMAL_ID),
            14_200,
            6,
            new \DateTimeImmutable('2026-08-22'),
            'Camille',
            $recordedAt,
        );

        $entity = $mapper->toEntity($record);

        self::assertSame(self::RECORD_ID, $entity->id->toString());
        self::assertSame(self::CLINIC_ID, $entity->clinicId->toString());
        self::assertSame(self::ANIMAL_ID, $entity->animalId->toString());
        self::assertSame(14_200, $entity->weightGrams);
        self::assertSame(6, $entity->bodyConditionScore);
        self::assertSame('2026-08-22', $entity->measuredOn->format('Y-m-d'));
        self::assertSame('Camille', $entity->authorName);
        self::assertSame($recordedAt, $entity->recordedAt);

        $restored = $mapper->toDomain($entity);

        self::assertTrue($restored->id()->equals($record->id()));
        self::assertTrue($restored->clinicId()->equals($record->clinicId()));
        self::assertTrue($restored->animalId()->equals($record->animalId()));
        self::assertSame($record->weightGrams(), $restored->weightGrams());
        self::assertSame($record->bodyConditionScore(), $restored->bodyConditionScore());
        self::assertSame('2026-08-22', $restored->measuredOn()->format('Y-m-d'));
        self::assertSame($record->authorName(), $restored->authorName());
        self::assertSame($record->recordedAt(), $restored->recordedAt());
    }

    public function testAPreventiveCareRecordSurvivesARoundTrip(): void
    {
        $mapper     = new PreventiveCareRecordMapper();
        $recordedAt = new \DateTimeImmutable('2026-03-02 11:00:00');
        $record     = PreventiveCareRecord::reconstitute(
            PreventiveCareRecordId::fromString(self::RECORD_ID),
            ClinicId::fromString(self::CLINIC_ID),
            AnimalId::fromString(self::ANIMAL_ID),
            PreventiveCareKind::VACCINATION,
            'CHPPiL',
            new \DateTimeImmutable('2026-03-02'),
            'VX-77219',
            new \DateTimeImmutable('2027-03-02'),
            'Dr Nguyen',
            $recordedAt,
        );

        $entity = $mapper->toEntity($record);

        self::assertSame(self::RECORD_ID, $entity->id->toString());
        self::assertSame(self::CLINIC_ID, $entity->clinicId->toString());
        self::assertSame(self::ANIMAL_ID, $entity->animalId->toString());
        self::assertSame(PreventiveCareKind::VACCINATION, $entity->kind);
        self::assertSame('CHPPiL', $entity->label);
        self::assertSame('2026-03-02', $entity->administeredOn->format('Y-m-d'));
        self::assertSame('VX-77219', $entity->batchNumber);
        self::assertSame('2027-03-02', $entity->nextDueOn?->format('Y-m-d'));
        self::assertSame('Dr Nguyen', $entity->authorName);
        self::assertSame($recordedAt, $entity->recordedAt);

        $restored = $mapper->toDomain($entity);

        self::assertTrue($restored->id()->equals($record->id()));
        self::assertTrue($restored->clinicId()->equals($record->clinicId()));
        self::assertTrue($restored->animalId()->equals($record->animalId()));
        self::assertSame($record->kind(), $restored->kind());
        self::assertSame($record->label(), $restored->label());
        self::assertSame('2026-03-02', $restored->administeredOn()->format('Y-m-d'));
        self::assertSame($record->batchNumber(), $restored->batchNumber());
        self::assertSame('2027-03-02', $restored->nextDueOn()?->format('Y-m-d'));
        self::assertSame($record->authorName(), $restored->authorName());
        self::assertSame($record->recordedAt(), $restored->recordedAt());
    }
}
