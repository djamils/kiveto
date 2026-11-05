<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Query;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Animal\Application\Port\WeightRecordReadRepositoryInterface;
use App\Context\Animal\Application\Query\ListAnimalWeightRecords\WeightRecordView;
use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal\MedicalAlertView;
use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimals\ListMedicalAlertsForAnimals;
use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimals\ListMedicalAlertsForAnimalsHandler;
use App\Context\Animal\Application\Query\ListWeightRecordsForAnimals\ListWeightRecordsForAnimals;
use App\Context\Animal\Application\Query\ListWeightRecordsForAnimals\ListWeightRecordsForAnimalsHandler;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

/** The batch reads of several animals: one repository call, each id once, nothing asked for no id. */
final class AnimalBatchReadsHandlersTest extends TestCase
{
    private const string CLINIC = '12345678-9abc-def0-1234-56789abcdef0';
    private const string REX    = '0192b000-0000-7000-8000-000000000001';
    private const string LUNA   = '0192b000-0000-7000-8000-000000000002';

    public function testTheAlertsOfSeveralAnimalsAreReadOnce(): void
    {
        $alerts = [self::REX => [new MedicalAlertView('m1', 'ALLERGY', 'Pénicilline', null)]];

        $repository = $this->createMock(AnimalReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('listMedicalAlerts')
            ->with(self::callback(static fn (ClinicId $id): bool => self::CLINIC === $id->toString()), self::identicalTo([self::REX, self::LUNA]))
            ->willReturn($alerts)
        ;

        self::assertSame($alerts, new ListMedicalAlertsForAnimalsHandler($repository)(new ListMedicalAlertsForAnimals(self::CLINIC, [self::REX, self::LUNA, self::REX])));
    }

    public function testNoAnimalReadsNoAlert(): void
    {
        $repository = $this->createMock(AnimalReadRepositoryInterface::class);
        $repository->expects(self::never())->method('listMedicalAlerts');

        self::assertSame([], new ListMedicalAlertsForAnimalsHandler($repository)(new ListMedicalAlertsForAnimals(self::CLINIC, [])));
    }

    public function testTheWeighingsOfSeveralAnimalsAreReadOnce(): void
    {
        $records = [self::LUNA => [new WeightRecordView('w1', 4200, null, new \DateTimeImmutable('2026-10-01'), 'Dr Lambert')]];

        $repository = $this->createMock(WeightRecordReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('listForAnimals')
            ->with(self::callback(static fn (ClinicId $id): bool => self::CLINIC === $id->toString()), self::identicalTo([self::LUNA, self::REX]))
            ->willReturn($records)
        ;

        self::assertSame($records, new ListWeightRecordsForAnimalsHandler($repository)(new ListWeightRecordsForAnimals(self::CLINIC, [self::LUNA, self::REX, self::LUNA])));
    }

    public function testNoAnimalReadsNoWeighing(): void
    {
        $repository = $this->createMock(WeightRecordReadRepositoryInterface::class);
        $repository->expects(self::never())->method('listForAnimals');

        self::assertSame([], new ListWeightRecordsForAnimalsHandler($repository)(new ListWeightRecordsForAnimals(self::CLINIC, [])));
    }
}
