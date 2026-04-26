<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Patient;

use App\Context\Patient\Domain\Exception\PatientNotFoundException;
use App\Context\Patient\Domain\Patient;
use App\Context\Patient\Domain\Repository\PatientRepositoryInterface;
use App\Context\Patient\Domain\ValueObject\AnimalLink;
use App\Context\Patient\Domain\ValueObject\ClinicId;
use App\Context\Patient\Domain\ValueObject\DisplayLabelKind;
use App\Context\Patient\Domain\ValueObject\PatientId;
use App\Context\Patient\Domain\ValueObject\PatientStatus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrinePatientRepositoryTest extends KernelTestCase
{
    use Factories;

    public function testSaveAndGetPatient(): void
    {
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');

        /** @var PatientRepositoryInterface $repo */
        $repo = static::getContainer()->get(PatientRepositoryInterface::class);

        $patientId = PatientId::fromString(Uuid::v7()->toString());
        $now       = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');

        $patient = Patient::createUnidentified(
            id: $patientId,
            clinicId: $clinicId,
            provisionalLabel: 'Chat roux inconnu',
            observedSpecies: 'cat',
            observedColor: 'roux',
            now: $now,
        );

        $repo->save($patient);

        $found = $repo->findById($clinicId, $patientId);

        self::assertNotNull($found);
        self::assertSame($patientId->toString(), $found->id()->toString());
        self::assertSame($clinicId->toString(), $found->clinicId()->toString());
        self::assertSame(PatientStatus::Active, $found->status());
        self::assertSame(DisplayLabelKind::Provisional, $found->displayLabel()->kind());
        self::assertSame('Chat roux inconnu', $found->displayLabel()->value());
        self::assertNull($found->animalLink());
        self::assertSame('cat', $found->observedSpecies());
        self::assertSame('roux', $found->observedColor());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $clinicId  = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $patientId = PatientId::fromString('01234567-89ab-cdef-0123-456789abcdef');

        /** @var PatientRepositoryInterface $repo */
        $repo = static::getContainer()->get(PatientRepositoryInterface::class);

        self::assertNull($repo->findById($clinicId, $patientId));
    }

    public function testGetThrowsExceptionWhenNotFound(): void
    {
        $clinicId  = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $patientId = PatientId::fromString('01234567-89ab-cdef-0123-456789abcdef');

        /** @var PatientRepositoryInterface $repo */
        $repo = static::getContainer()->get(PatientRepositoryInterface::class);

        $this->expectException(PatientNotFoundException::class);

        $repo->get($clinicId, $patientId);
    }

    public function testSaveUpdatesExistingPatient(): void
    {
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');

        /** @var PatientRepositoryInterface $repo */
        $repo = static::getContainer()->get(PatientRepositoryInterface::class);

        $patientId = PatientId::fromString(Uuid::v7()->toString());
        $now       = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');

        $patient = Patient::createUnidentified(
            id: $patientId,
            clinicId: $clinicId,
            provisionalLabel: 'Chien inconnu',
            observedSpecies: 'dog',
            observedColor: null,
            now: $now,
        );

        $repo->save($patient);

        // Load, link to animal, save again
        $found = $repo->findById($clinicId, $patientId);
        \assert(null !== $found);

        $animalId = Uuid::v7()->toString();
        $later    = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');

        $found->linkToAnimal(AnimalLink::fromAnimalId($animalId), $later);

        $repo->save($found);

        $updated = $repo->findById($clinicId, $patientId);
        \assert(null !== $updated);

        self::assertNotNull($updated->animalLink());
        self::assertSame($animalId, $updated->animalLink()->animalId());
        self::assertSame(PatientStatus::Active, $updated->status());
    }

    public function testSaveAndGetLinkedPatient(): void
    {
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');

        /** @var PatientRepositoryInterface $repo */
        $repo = static::getContainer()->get(PatientRepositoryInterface::class);

        $patientId  = PatientId::fromString(Uuid::v7()->toString());
        $animalId   = Uuid::v7()->toString();
        $animalLink = AnimalLink::fromAnimalId($animalId);
        $now        = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');

        $patient = Patient::createFromAnimal(
            id: $patientId,
            clinicId: $clinicId,
            animalLink: $animalLink,
            animalName: 'Rex',
            now: $now,
        );

        $repo->save($patient);

        $found = $repo->get($clinicId, $patientId);

        self::assertSame(PatientStatus::Active, $found->status());
        self::assertSame(DisplayLabelKind::FromAnimal, $found->displayLabel()->kind());
        self::assertSame('Rex', $found->displayLabel()->value());
        self::assertNotNull($found->animalLink());
        self::assertSame($animalId, $found->animalLink()->animalId());
    }
}
