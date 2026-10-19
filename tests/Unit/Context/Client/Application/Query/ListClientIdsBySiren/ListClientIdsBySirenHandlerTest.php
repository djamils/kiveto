<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Query\ListClientIdsBySiren;

use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Application\Query\ListClientIdsBySiren\ListClientIdsBySiren;
use App\Context\Client\Application\Query\ListClientIdsBySiren\ListClientIdsBySirenHandler;
use App\Context\Client\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class ListClientIdsBySirenHandlerTest extends TestCase
{
    private const string CLINIC = '12345678-9abc-def0-1234-56789abcdef0';

    public function testListsTheClientsOfTheClinicCarryingTheNormalisedSiren(): void
    {
        $repository = $this->createMock(ClientReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findIdsBySiren')
            ->with(ClinicId::fromString(self::CLINIC), '523456788')
            ->willReturn(['01234567-89ab-cdef-0123-456789abcdef'])
        ;

        $ids = (new ListClientIdsBySirenHandler($repository))(new ListClientIdsBySiren(self::CLINIC, '523 456 788 00012'));

        self::assertSame(['01234567-89ab-cdef-0123-456789abcdef'], $ids);
    }

    public function testAnInvalidSirenBelongsToNobody(): void
    {
        $repository = $this->createMock(ClientReadRepositoryInterface::class);
        $repository->expects(self::never())->method('findIdsBySiren');

        self::assertSame([], (new ListClientIdsBySirenHandler($repository))(new ListClientIdsBySiren(self::CLINIC, '523456789')));
    }
}
