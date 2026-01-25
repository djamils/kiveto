<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Application\Query\GetClientById;

use App\Client\Application\Port\ClientReadRepositoryInterface;
use App\Client\Application\Query\GetClientById\ClientView;
use App\Client\Application\Query\GetClientById\GetClientById;
use App\Client\Application\Query\GetClientById\GetClientByIdHandler;
use App\Client\Domain\ValueObject\ClientId;
use App\Clinic\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class GetClientByIdHandlerTest extends TestCase
{
    public function testHandleReturnsClientView(): void
    {
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $clientId = ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef');

        $clientView = new ClientView(
            id: '01234567-89ab-cdef-0123-456789abcdef',
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            firstName: 'John',
            lastName: 'Doe',
            status: 'active',
            contactMethods: [],
            postalAddress: null,
            createdAt: '2024-01-01T10:00:00+00:00',
            updatedAt: '2024-01-01T10:00:00+00:00'
        );

        $query = new GetClientById(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            clientId: '01234567-89ab-cdef-0123-456789abcdef'
        );

        $readRepository = $this->createMock(ClientReadRepositoryInterface::class);
        $readRepository->expects(self::once())
            ->method('findById')
            ->with(
                self::callback(fn (ClinicId $id) => $id->equals($clinicId)),
                self::callback(fn (ClientId $id) => $id->equals($clientId))
            )
            ->willReturn($clientView)
        ;

        $handler = new GetClientByIdHandler($readRepository);
        $result  = $handler($query);

        self::assertSame($clientView, $result);
    }

    public function testHandleReturnsNullWhenClientNotFound(): void
    {
        $query = new GetClientById(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            clientId: '01234567-89ab-cdef-0123-456789abcdef'
        );

        $readRepository = $this->createMock(ClientReadRepositoryInterface::class);
        $readRepository->expects(self::once())
            ->method('findById')
            ->willReturn(null)
        ;

        $handler = new GetClientByIdHandler($readRepository);
        $result  = $handler($query);

        self::assertNull($result);
    }
}
