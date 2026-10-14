<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Query\ListAnimalPreventiveCare;

use App\Context\Animal\Application\Port\PreventiveCareRecordReadRepositoryInterface;
use App\Context\Animal\Application\Query\ListAnimalPreventiveCare\ListAnimalPreventiveCare;
use App\Context\Animal\Application\Query\ListAnimalPreventiveCare\ListAnimalPreventiveCareHandler;
use App\Context\Animal\Application\Query\ListAnimalPreventiveCare\PreventiveCareRecordView;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class ListAnimalPreventiveCareHandlerTest extends TestCase
{
    public function testAsksTheReadRepositoryForTheAnimalOfTheClinic(): void
    {
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';
        $animalId = '0192b000-0000-7000-8000-000000000001';
        $on       = new \DateTimeImmutable('2026-08-22');
        $view     = new PreventiveCareRecordView(
            '0192c000-0000-7000-8000-000000000001',
            'vaccination',
            'CHPPiL',
            $on,
            'VX-77219',
            null,
            'Dr Nguyen',
        );

        $readRepository = $this->createMock(PreventiveCareRecordReadRepositoryInterface::class);
        $readRepository->expects(self::once())
            ->method('listForAnimal')
            ->with(
                self::callback(static fn (ClinicId $id): bool => $clinicId === $id->toString()),
                self::callback(static fn (AnimalId $id): bool => $animalId === $id->toString()),
            )
            ->willReturn([$view])
        ;

        $result = (new ListAnimalPreventiveCareHandler($readRepository))(new ListAnimalPreventiveCare($clinicId, $animalId));

        self::assertSame([$view], $result);
        self::assertSame('vaccination', $result[0]->kind);
        self::assertSame('CHPPiL', $result[0]->label);
        self::assertSame($on, $result[0]->administeredOn);
        self::assertSame('VX-77219', $result[0]->batchNumber);
        self::assertNull($result[0]->nextDueOn);
        self::assertSame('Dr Nguyen', $result[0]->authorName);
    }
}
