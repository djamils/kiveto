<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Query\CountClients;

use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Application\Query\CountClients\CountClients;
use App\Context\Client\Application\Query\CountClients\CountClientsHandler;
use App\Context\Client\Application\Query\SearchClients\SearchClientsCriteria;
use App\Context\Client\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class CountClientsHandlerTest extends TestCase
{
    public function testHandleReturnsCountFromRepository(): void
    {
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');

        $query = new CountClients(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            searchTerm: 'John',
            status: 'active',
        );

        $readRepository = $this->createMock(ClientReadRepositoryInterface::class);
        $readRepository->expects(self::once())
            ->method('countBy')
            ->with(
                self::callback(fn (ClinicId $id) => $id->equals($clinicId)),
                self::callback(
                    static fn (SearchClientsCriteria $criteria): bool => 'John' === $criteria->searchTerm
                        && 'active' === $criteria->status,
                ),
            )
            ->willReturn(7)
        ;

        $handler = new CountClientsHandler($readRepository);

        self::assertSame(7, $handler($query));
    }

    public function testHandleWithNullFiltersDelegatesDefaultsToRepository(): void
    {
        $readRepository = $this->createMock(ClientReadRepositoryInterface::class);
        $readRepository->expects(self::once())
            ->method('countBy')
            ->willReturn(0)
        ;

        $handler = new CountClientsHandler($readRepository);
        $query   = new CountClients(clinicId: '12345678-9abc-def0-1234-56789abcdef0');

        self::assertSame(0, $handler($query));
    }
}
