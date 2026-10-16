<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Query\ListClientIdsByPhone;

use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Application\Query\ListClientIdsByPhone\ListClientIdsByPhone;
use App\Context\Client\Application\Query\ListClientIdsByPhone\ListClientIdsByPhoneHandler;
use App\Context\Client\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class ListClientIdsByPhoneHandlerTest extends TestCase
{
    private const string CLINIC = '12345678-9abc-def0-1234-56789abcdef0';

    public function testListsTheClientsOfTheClinicHavingThisNumber(): void
    {
        $repository = $this->createMock(ClientReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findIdsByPhone')
            ->with(ClinicId::fromString(self::CLINIC), '+33612345678')
            ->willReturn(['01234567-89ab-cdef-0123-456789abcdef'])
        ;

        $ids = (new ListClientIdsByPhoneHandler($repository))(new ListClientIdsByPhone(self::CLINIC, ' +33612345678 '));

        self::assertSame(['01234567-89ab-cdef-0123-456789abcdef'], $ids);
    }

    public function testABlankNumberBelongsToNobody(): void
    {
        $repository = $this->createMock(ClientReadRepositoryInterface::class);
        $repository->expects(self::never())->method('findIdsByPhone');

        self::assertSame([], (new ListClientIdsByPhoneHandler($repository))(new ListClientIdsByPhone(self::CLINIC, '  ')));
    }
}
