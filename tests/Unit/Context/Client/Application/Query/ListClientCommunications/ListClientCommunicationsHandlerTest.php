<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Query\ListClientCommunications;

use App\Context\Client\Application\Port\ClientCommunicationReadRepositoryInterface;
use App\Context\Client\Application\Query\ListClientCommunications\ClientCommunicationView;
use App\Context\Client\Application\Query\ListClientCommunications\ListClientCommunications;
use App\Context\Client\Application\Query\ListClientCommunications\ListClientCommunicationsHandler;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class ListClientCommunicationsHandlerTest extends TestCase
{
    public function testAsksTheReadRepositoryForTheClientOfTheClinic(): void
    {
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';
        $clientId = '01234567-89ab-cdef-0123-456789abcdef';
        $at       = new \DateTimeImmutable('2026-07-22 09:15:00');
        $view     = new ClientCommunicationView(
            '0192a000-0000-7000-8000-000000000002',
            'phone',
            'inbound',
            'Appel entrant',
            null,
            'Camille',
            $at,
        );

        $readRepository = $this->createMock(ClientCommunicationReadRepositoryInterface::class);
        $readRepository->expects(self::once())
            ->method('listForClient')
            ->with(
                self::callback(static fn (ClinicId $id): bool => $clinicId === $id->toString()),
                self::callback(static fn (ClientId $id): bool => $clientId === $id->toString()),
            )
            ->willReturn([$view])
        ;

        $result = (new ListClientCommunicationsHandler($readRepository))(new ListClientCommunications($clinicId, $clientId));

        self::assertSame([$view], $result);
        self::assertSame('phone', $result[0]->channel);
        self::assertSame('inbound', $result[0]->direction);
        self::assertSame('Appel entrant', $result[0]->subject);
        self::assertNull($result[0]->body);
        self::assertSame('Camille', $result[0]->authorName);
        self::assertSame($at, $result[0]->occurredAt);
    }
}
