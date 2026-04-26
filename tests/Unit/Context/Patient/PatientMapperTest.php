<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Patient;

use App\Context\Patient\Domain\Patient;
use App\Context\Patient\Domain\ValueObject\AnimalLink;
use App\Context\Patient\Domain\ValueObject\ClinicId;
use App\Context\Patient\Domain\ValueObject\DisplayLabel;
use App\Context\Patient\Domain\ValueObject\DisplayLabelKind;
use App\Context\Patient\Domain\ValueObject\PatientId;
use App\Context\Patient\Domain\ValueObject\PatientStatus;
use App\Context\Patient\Infrastructure\Persistence\Doctrine\PatientMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class PatientMapperTest extends TestCase
{
    private PatientMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new PatientMapper();
    }

    public function testToDomainToEntitySymmetryForUnidentifiedPatient(): void
    {
        $id       = PatientId::fromString(Uuid::v7()->toString());
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $now      = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');

        $original = Patient::reconstituteFromPersistence(
            id: $id,
            clinicId: $clinicId,
            status: PatientStatus::Active,
            displayLabel: DisplayLabel::provisional('Chat roux'),
            animalLink: null,
            observedSpecies: 'cat',
            observedColor: 'roux',
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $entity        = $this->mapper->toEntity($original);
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame($id->toString(), $reconstituted->id()->toString());
        self::assertSame($clinicId->toString(), $reconstituted->clinicId()->toString());
        self::assertSame(PatientStatus::Active, $reconstituted->status());
        self::assertSame(DisplayLabelKind::Provisional, $reconstituted->displayLabel()->kind());
        self::assertSame('Chat roux', $reconstituted->displayLabel()->value());
        self::assertNull($reconstituted->animalLink());
        self::assertSame('cat', $reconstituted->observedSpecies());
        self::assertSame('roux', $reconstituted->observedColor());
        self::assertSame(1, $reconstituted->version());
        self::assertSame($now->getTimestamp(), $reconstituted->createdAt()->getTimestamp());
        self::assertSame($now->getTimestamp(), $reconstituted->updatedAt()->getTimestamp());
    }

    public function testToDomainToEntitySymmetryForLinkedPatient(): void
    {
        $id         = PatientId::fromString(Uuid::v7()->toString());
        $clinicId   = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $animalId   = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $now        = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $animalLink = AnimalLink::fromAnimalId($animalId);

        $original = Patient::reconstituteFromPersistence(
            id: $id,
            clinicId: $clinicId,
            status: PatientStatus::Active,
            displayLabel: DisplayLabel::fromAnimal('Rex'),
            animalLink: $animalLink,
            observedSpecies: null,
            observedColor: null,
            version: 2,
            createdAt: $now,
            updatedAt: $now,
        );

        $entity        = $this->mapper->toEntity($original);
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame($id->toString(), $reconstituted->id()->toString());
        self::assertSame(DisplayLabelKind::FromAnimal, $reconstituted->displayLabel()->kind());
        self::assertSame('Rex', $reconstituted->displayLabel()->value());
        self::assertNotNull($reconstituted->animalLink());
        self::assertSame($animalId, $reconstituted->animalLink()->animalId());
        self::assertNull($reconstituted->observedSpecies());
        self::assertNull($reconstituted->observedColor());
        self::assertSame(2, $reconstituted->version());
    }

    public function testToDomainToEntitySymmetryForArchivedPatient(): void
    {
        $id       = PatientId::fromString(Uuid::v7()->toString());
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $now      = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');

        $original = Patient::reconstituteFromPersistence(
            id: $id,
            clinicId: $clinicId,
            status: PatientStatus::Archived,
            displayLabel: DisplayLabel::custom('Custom Label'),
            animalLink: null,
            observedSpecies: null,
            observedColor: null,
            version: 5,
            createdAt: $now,
            updatedAt: $now,
        );

        $entity        = $this->mapper->toEntity($original);
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame(PatientStatus::Archived, $reconstituted->status());
        self::assertSame(DisplayLabelKind::Custom, $reconstituted->displayLabel()->kind());
        self::assertSame('Custom Label', $reconstituted->displayLabel()->value());
        self::assertSame(5, $reconstituted->version());
    }
}
