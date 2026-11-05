<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Animal\Infrastructure\Persistence\Doctrine;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Animal\Application\Port\WeightRecordReadRepositoryInterface;
use App\Context\Animal\Application\Query\ListAnimalWeightRecords\WeightRecordView;
use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal\MedicalAlertView;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\MedicalAlertKind;
use App\Context\Animal\Infrastructure\Persistence\Doctrine\Entity\AnimalEntity;
use App\Context\Animal\Infrastructure\Persistence\Doctrine\Entity\MedicalAlertEntity;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Animal\Factory\WeightRecordEntityFactory;
use App\Tests\Support\Database\QueryCounter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/** The alerts and the weighings of several animals, each read in one query, as the file of one animal reads them. */
final class AnimalBatchReadsTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC    = '12345678-9abc-def0-1234-56789abcdef0';
    private const string OTHER     = '88888888-8888-7888-8888-888888888888';
    private const string REX       = '0192b000-0000-7000-8000-0000000000b1';
    private const string LUNA      = '0192b000-0000-7000-8000-0000000000b2';
    private const string NALA      = '0192b000-0000-7000-8000-0000000000b3';
    private const string ELSEWHERE = '0192b000-0000-7000-8000-0000000000b4';

    public function testTheAlertsOfSeveralAnimalsInOneQuery(): void
    {
        foreach ([self::REX => self::CLINIC, self::LUNA => self::CLINIC, self::NALA => self::CLINIC, self::ELSEWHERE => self::OTHER] as $id => $clinicId) {
            AnimalEntityFactory::new()->withClinicId($clinicId)->withName('Animal')->dog()->active()->alive()->create(['id' => Uuid::fromString($id)]);
        }
        $this->alert('0192c000-0000-7000-8000-0000000000a2', self::REX, MedicalAlertKind::BEHAVIOR, 'Mord', 'Muselière');
        $this->alert('0192c000-0000-7000-8000-0000000000a1', self::REX, MedicalAlertKind::ALLERGY, 'Pénicilline', null);
        $this->alert('0192c000-0000-7000-8000-0000000000a3', self::LUNA, MedicalAlertKind::CHRONIC_CONDITION, 'Diabète', null);
        $this->alert('0192c000-0000-7000-8000-0000000000a4', self::ELSEWHERE, MedicalAlertKind::ALLERGY, 'Ailleurs', null);
        $this->clear();

        QueryCounter::reset();
        $alerts  = $this->animals()->listMedicalAlerts(ClinicId::fromString(self::CLINIC), [self::REX, self::LUNA, self::NALA, self::ELSEWHERE]);
        $queries = QueryCounter::count();

        // Nala has none; another clinic's animal is left out.
        self::assertSame([self::REX, self::LUNA], array_keys($alerts));
        self::assertSame(
            [['ALLERGY', 'Pénicilline', null], ['BEHAVIOR', 'Mord', 'Muselière']],
            array_map(static fn (MedicalAlertView $a): array => [$a->kind, $a->label, $a->note], $alerts[self::REX]),
        );
        self::assertSame('0192c000-0000-7000-8000-0000000000a3', $alerts[self::LUNA][0]->id);
        self::assertSame(1, $queries);
    }

    public function testTheWeighingsOfSeveralAnimalsInOneQueryNewestFirst(): void
    {
        $this->weighing(self::REX, self::CLINIC, 30100, '2026-09-01');
        $this->weighing(self::REX, self::CLINIC, 31200, '2026-10-15');
        $this->weighing(self::LUNA, self::CLINIC, 4200, '2026-10-01');
        $this->weighing(self::ELSEWHERE, self::OTHER, 9000, '2026-10-01');
        $this->clear();

        QueryCounter::reset();
        $records = $this->weights()->listForAnimals(ClinicId::fromString(self::CLINIC), [self::REX, self::LUNA, self::NALA, self::ELSEWHERE]);
        $queries = QueryCounter::count();

        $grams = array_map(static fn (array $list): array => array_map(static fn (WeightRecordView $r): int => $r->weightGrams, $list), $records);
        ksort($grams);
        self::assertSame([self::REX => [31200, 30100], self::LUNA => [4200]], $grams);
        self::assertSame(1, $queries);

        // The same weighings as the animal's own list.
        $one = $this->weights()->listForAnimal(ClinicId::fromString(self::CLINIC), AnimalId::fromString(self::REX));
        self::assertSame(array_map(static fn (WeightRecordView $r): string => $r->id, $one), array_map(static fn (WeightRecordView $r): string => $r->id, $records[self::REX]));
    }

    private function alert(string $id, string $animalId, MedicalAlertKind $kind, string $label, ?string $note): void
    {
        $entityManager = $this->entityManager();
        $animal        = $entityManager->find(AnimalEntity::class, Uuid::fromString($animalId));
        \assert($animal instanceof AnimalEntity);

        $alert = new MedicalAlertEntity();
        $alert->setId(Uuid::fromString($id));
        $alert->setAnimal($animal);
        $alert->setKind($kind);
        $alert->setLabel($label);
        $alert->setNote($note);
        $entityManager->persist($alert);
        $entityManager->flush();
    }

    private function weighing(string $animalId, string $clinicId, int $grams, string $on): void
    {
        WeightRecordEntityFactory::new()->forAnimal($clinicId, $animalId)->weighing($grams, new \DateTimeImmutable($on))->create();
    }

    private function clear(): void
    {
        $this->entityManager()->clear();
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        return $entityManager;
    }

    private function animals(): AnimalReadRepositoryInterface
    {
        $repository = self::getContainer()->get(AnimalReadRepositoryInterface::class);
        \assert($repository instanceof AnimalReadRepositoryInterface);

        return $repository;
    }

    private function weights(): WeightRecordReadRepositoryInterface
    {
        $repository = self::getContainer()->get(WeightRecordReadRepositoryInterface::class);
        \assert($repository instanceof WeightRecordReadRepositoryInterface);

        return $repository;
    }
}
