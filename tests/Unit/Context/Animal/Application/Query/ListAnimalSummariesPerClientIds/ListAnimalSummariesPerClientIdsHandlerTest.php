<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds\AnimalSummary;
use App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds\ListAnimalSummariesPerClientIds;
use App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds\ListAnimalSummariesPerClientIdsHandler;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class ListAnimalSummariesPerClientIdsHandlerTest extends TestCase
{
    public function testReturnsEmptyArrayWhenClientIdsIsEmpty(): void
    {
        $repository = $this->createMock(AnimalReadRepositoryInterface::class);
        $repository->expects(self::never())->method('listAnimalSummariesByPrimaryOwnerClientIds');

        $handler = new ListAnimalSummariesPerClientIdsHandler($repository);
        $result  = $handler(new ListAnimalSummariesPerClientIds(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            clientIds: [],
        ));

        self::assertSame([], $result);
    }

    public function testDelegatesSearchToRepository(): void
    {
        $clinicId  = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $clientIds = [
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        ];
        $expected = [
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa' => [
                new AnimalSummary('11111111-1111-1111-1111-111111111111', 'Luna', 'cat'),
                new AnimalSummary('22222222-2222-2222-2222-222222222222', 'Max', 'dog'),
            ],
            'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb' => [],
        ];

        $repository = $this->createMock(AnimalReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('listAnimalSummariesByPrimaryOwnerClientIds')
            ->with(
                self::callback(fn (ClinicId $id) => $id->equals($clinicId)),
                self::identicalTo($clientIds),
                self::identicalTo(5),
            )
            ->willReturn($expected)
        ;

        $handler = new ListAnimalSummariesPerClientIdsHandler($repository);
        $result  = $handler(new ListAnimalSummariesPerClientIds(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            clientIds: $clientIds,
            limit: 5,
        ));

        self::assertSame($expected, $result);
    }
}
