<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Application\Query\SearchClients;

use App\Client\Application\Port\ClientReadRepositoryInterface;
use App\Client\Application\Query\SearchClients\SearchClients;
use App\Client\Application\Query\SearchClients\SearchClientsHandler;
use App\Clinic\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class SearchClientsHandlerTest extends TestCase
{
    public function testHandleReturnsSearchResults(): void
    {
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');

        $expectedResult = [
            'items' => [],
            'total' => 0,
        ];

        $query = new SearchClients(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            searchTerm: 'John',
            status: 'active',
            page: 1,
            limit: 20
        );

        $readRepository = $this->createMock(ClientReadRepositoryInterface::class);
        $readRepository->expects(self::once())
            ->method('search')
            ->with(
                self::callback(fn (ClinicId $id) => $id->equals($clinicId)),
                self::anything()
            )
            ->willReturn($expectedResult)
        ;

        $handler = new SearchClientsHandler($readRepository);
        $result  = $handler($query);

        self::assertSame($expectedResult, $result);
        self::assertArrayHasKey('items', $result);
        self::assertArrayHasKey('total', $result);
    }

    public function testHandleWithNullSearchTermAndStatus(): void
    {
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');

        $expectedResult = [
            'items' => [],
            'total' => 10,
        ];

        $query = new SearchClients(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            searchTerm: null,
            status: null,
            page: 2,
            limit: 10
        );

        $readRepository = $this->createMock(ClientReadRepositoryInterface::class);
        $readRepository->expects(self::once())
            ->method('search')
            ->willReturn($expectedResult)
        ;

        $handler = new SearchClientsHandler($readRepository);
        $result  = $handler($query);

        self::assertSame($expectedResult, $result);
    }
}
