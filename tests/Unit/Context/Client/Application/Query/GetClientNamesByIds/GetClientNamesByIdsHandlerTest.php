<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Query\GetClientNamesByIds;

use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Application\Query\GetClientNamesByIds\GetClientNamesByIds;
use App\Context\Client\Application\Query\GetClientNamesByIds\GetClientNamesByIdsHandler;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class GetClientNamesByIdsHandlerTest extends TestCase
{
    public function testReturnsEmptyArrayWhenClientIdsIsEmpty(): void
    {
        $repository = $this->createMock(ClientReadRepositoryInterface::class);
        $repository->expects(self::never())->method('findFullNamesByIds');

        $handler = new GetClientNamesByIdsHandler($repository);
        $result  = $handler(new GetClientNamesByIds(
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
            'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa' => 'Marie Dupont',
            'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb' => 'Jean Martin',
        ];

        $repository = $this->createMock(ClientReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findFullNamesByIds')
            ->with(
                self::callback(fn (ClinicId $id) => $id->equals($clinicId)),
                self::identicalTo($clientIds),
            )
            ->willReturn($expected)
        ;

        $handler = new GetClientNamesByIdsHandler($repository);
        $result  = $handler(new GetClientNamesByIds(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            clientIds: $clientIds,
        ));

        self::assertSame($expected, $result);
    }
}
