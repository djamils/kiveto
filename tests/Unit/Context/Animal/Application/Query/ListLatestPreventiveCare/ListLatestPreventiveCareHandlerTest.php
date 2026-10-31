<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Query\ListLatestPreventiveCare;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Animal\Application\Query\ListLatestPreventiveCare\LatestPreventiveCareView;
use App\Context\Animal\Application\Query\ListLatestPreventiveCare\ListLatestPreventiveCare;
use App\Context\Animal\Application\Query\ListLatestPreventiveCare\ListLatestPreventiveCareHandler;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class ListLatestPreventiveCareHandlerTest extends TestCase
{
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';

    public function testReturnsNothingWithoutQueryingForNoAnimal(): void
    {
        $repository = $this->createMock(AnimalReadRepositoryInterface::class);
        $repository->expects(self::never())->method('listLatestPreventiveCare');

        $handler = new ListLatestPreventiveCareHandler($repository);

        self::assertSame([], $handler(new ListLatestPreventiveCare(self::CLINIC_ID, [])));
    }

    public function testDelegatesToTheReadRepository(): void
    {
        $animalIds = ['11111111-1111-1111-1111-111111111111'];
        $expected  = [new LatestPreventiveCareView(
            animalId: '11111111-1111-1111-1111-111111111111',
            kind: 'vaccination',
            label: 'Rage',
            administeredOn: new \DateTimeImmutable('2026-02-20'),
            batchNumber: 'RB-2',
            nextDueOn: null,
        )];

        $repository = $this->createMock(AnimalReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('listLatestPreventiveCare')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
                self::identicalTo($animalIds),
            )
            ->willReturn($expected)
        ;

        $handler = new ListLatestPreventiveCareHandler($repository);

        self::assertSame($expected, $handler(new ListLatestPreventiveCare(self::CLINIC_ID, $animalIds)));
    }
}
