<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Query\ListAnimalWeightRecords;

use App\Context\Animal\Application\Port\WeightRecordReadRepositoryInterface;
use App\Context\Animal\Application\Query\ListAnimalWeightRecords\ListAnimalWeightRecords;
use App\Context\Animal\Application\Query\ListAnimalWeightRecords\ListAnimalWeightRecordsHandler;
use App\Context\Animal\Application\Query\ListAnimalWeightRecords\WeightRecordView;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class ListAnimalWeightRecordsHandlerTest extends TestCase
{
    public function testAsksTheReadRepositoryForTheAnimalOfTheClinic(): void
    {
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';
        $animalId = '0192b000-0000-7000-8000-000000000001';
        $on       = new \DateTimeImmutable('2026-08-22');
        $view     = new WeightRecordView('0192c000-0000-7000-8000-000000000001', 14_200, 6, $on, 'Camille');

        $readRepository = $this->createMock(WeightRecordReadRepositoryInterface::class);
        $readRepository->expects(self::once())
            ->method('listForAnimal')
            ->with(
                self::callback(static fn (ClinicId $id): bool => $clinicId === $id->toString()),
                self::callback(static fn (AnimalId $id): bool => $animalId === $id->toString()),
            )
            ->willReturn([$view])
        ;

        $result = (new ListAnimalWeightRecordsHandler($readRepository))(new ListAnimalWeightRecords($clinicId, $animalId));

        self::assertSame([$view], $result);
        self::assertSame(14_200, $result[0]->weightGrams);
        self::assertSame(6, $result[0]->bodyConditionScore);
        self::assertSame($on, $result[0]->measuredOn);
        self::assertSame('Camille', $result[0]->authorName);
    }
}
