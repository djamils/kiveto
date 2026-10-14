<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Animal\Infrastructure\Persistence\Doctrine;

use App\Context\Animal\Application\Port\PreventiveCareRecordReadRepositoryInterface;
use App\Context\Animal\Application\Port\WeightRecordReadRepositoryInterface;
use App\Context\Animal\Domain\PreventiveCareRecord;
use App\Context\Animal\Domain\Repository\PreventiveCareRecordRepositoryInterface;
use App\Context\Animal\Domain\Repository\WeightRecordRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\PreventiveCareKind;
use App\Context\Animal\Domain\ValueObject\PreventiveCareRecordId;
use App\Context\Animal\Domain\ValueObject\WeightRecordId;
use App\Context\Animal\Domain\WeightRecord;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineSheetRecordRepositoriesTest extends KernelTestCase
{
    use Factories;

    private const string ANIMAL_ID    = '0192b000-0000-7000-8000-000000000001';
    private const string CLINIC_ID    = '12345678-9abc-def0-1234-56789abcdef0';
    private const string OTHER_ANIMAL = '0192b000-0000-7000-8000-000000000099';
    private const string OTHER_CLINIC = '88888888-8888-7888-8888-888888888888';

    public function testListsTheWeighingsOfOneAnimalNewestFirst(): void
    {
        $this->weigh('0192c000-0000-7000-8000-000000000001', self::CLINIC_ID, self::ANIMAL_ID, 13_400, null, '2026-05-14');
        $this->weigh('0192c000-0000-7000-8000-000000000002', self::CLINIC_ID, self::ANIMAL_ID, 14_200, 6, '2026-08-22');
        // Another animal and another clinic must not leak into the list.
        $this->weigh('0192c000-0000-7000-8000-000000000003', self::CLINIC_ID, self::OTHER_ANIMAL, 4_800, null, '2026-08-23');
        $this->weigh('0192c000-0000-7000-8000-000000000004', self::OTHER_CLINIC, self::ANIMAL_ID, 9_900, null, '2026-08-24');

        $readRepository = static::getContainer()->get(WeightRecordReadRepositoryInterface::class);
        \assert($readRepository instanceof WeightRecordReadRepositoryInterface);

        $records = $readRepository->listForAnimal(
            ClinicId::fromString(self::CLINIC_ID),
            AnimalId::fromString(self::ANIMAL_ID),
        );

        self::assertCount(2, $records);

        self::assertSame('0192c000-0000-7000-8000-000000000002', $records[0]->id);
        self::assertSame(14_200, $records[0]->weightGrams);
        self::assertSame(6, $records[0]->bodyConditionScore);
        self::assertSame('2026-08-22', $records[0]->measuredOn->format('Y-m-d'));
        self::assertSame('Camille', $records[0]->authorName);

        self::assertSame('0192c000-0000-7000-8000-000000000001', $records[1]->id);
        self::assertSame(13_400, $records[1]->weightGrams);
        self::assertNull($records[1]->bodyConditionScore);
    }

    public function testListsThePreventiveActsOfOneAnimalNewestFirst(): void
    {
        $this->prevent('0192c000-0000-7000-8000-000000000011', self::CLINIC_ID, self::ANIMAL_ID, 'Rage', '2024-07-03', 'RB-40118', '2027-07-03');
        $this->prevent('0192c000-0000-7000-8000-000000000012', self::CLINIC_ID, self::ANIMAL_ID, 'CHPPiL', '2026-03-02', null, null);
        // Another animal and another clinic must not leak into the list.
        $this->prevent('0192c000-0000-7000-8000-000000000013', self::CLINIC_ID, self::OTHER_ANIMAL, 'Typhus', '2026-04-01', null, null);
        $this->prevent('0192c000-0000-7000-8000-000000000014', self::OTHER_CLINIC, self::ANIMAL_ID, 'Leucose', '2026-04-02', null, null);

        $readRepository = static::getContainer()->get(PreventiveCareRecordReadRepositoryInterface::class);
        \assert($readRepository instanceof PreventiveCareRecordReadRepositoryInterface);

        $records = $readRepository->listForAnimal(
            ClinicId::fromString(self::CLINIC_ID),
            AnimalId::fromString(self::ANIMAL_ID),
        );

        self::assertCount(2, $records);

        self::assertSame('0192c000-0000-7000-8000-000000000012', $records[0]->id);
        self::assertSame('CHPPiL', $records[0]->label);
        self::assertNull($records[0]->batchNumber);
        self::assertNull($records[0]->nextDueOn);

        self::assertSame('0192c000-0000-7000-8000-000000000011', $records[1]->id);
        self::assertSame('vaccination', $records[1]->kind);
        self::assertSame('Rage', $records[1]->label);
        self::assertSame('2024-07-03', $records[1]->administeredOn->format('Y-m-d'));
        self::assertSame('RB-40118', $records[1]->batchNumber);
        self::assertSame('2027-07-03', $records[1]->nextDueOn?->format('Y-m-d'));
        self::assertSame('Dr Meyer', $records[1]->authorName);
    }

    public function testReturnsEmptyListsForAnAnimalWithNothingRecorded(): void
    {
        $weights = static::getContainer()->get(WeightRecordReadRepositoryInterface::class);
        \assert($weights instanceof WeightRecordReadRepositoryInterface);
        $prevention = static::getContainer()->get(PreventiveCareRecordReadRepositoryInterface::class);
        \assert($prevention instanceof PreventiveCareRecordReadRepositoryInterface);

        $clinicId = ClinicId::fromString(self::CLINIC_ID);
        $animalId = AnimalId::fromString(self::ANIMAL_ID);

        self::assertSame([], $weights->listForAnimal($clinicId, $animalId));
        self::assertSame([], $prevention->listForAnimal($clinicId, $animalId));
    }

    private function weigh(string $id, string $clinicId, string $animalId, int $grams, ?int $score, string $on): void
    {
        $repository = static::getContainer()->get(WeightRecordRepositoryInterface::class);
        \assert($repository instanceof WeightRecordRepositoryInterface);

        $repository->save(WeightRecord::record(
            WeightRecordId::fromString($id),
            ClinicId::fromString($clinicId),
            AnimalId::fromString($animalId),
            $grams,
            $score,
            new \DateTimeImmutable($on),
            'Camille',
            new \DateTimeImmutable($on . ' 18:00:00'),
        ));
    }

    private function prevent(
        string $id,
        string $clinicId,
        string $animalId,
        string $label,
        string $on,
        ?string $batch,
        ?string $nextDueOn,
    ): void {
        $repository = static::getContainer()->get(PreventiveCareRecordRepositoryInterface::class);
        \assert($repository instanceof PreventiveCareRecordRepositoryInterface);

        $repository->save(PreventiveCareRecord::record(
            PreventiveCareRecordId::fromString($id),
            ClinicId::fromString($clinicId),
            AnimalId::fromString($animalId),
            PreventiveCareKind::VACCINATION,
            $label,
            new \DateTimeImmutable($on),
            $batch,
            null === $nextDueOn ? null : new \DateTimeImmutable($nextDueOn),
            'Dr Meyer',
            new \DateTimeImmutable($on . ' 18:00:00'),
        ));
    }
}
