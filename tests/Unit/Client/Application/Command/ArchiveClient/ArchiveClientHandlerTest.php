<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Application\Command\ArchiveClient;

use App\Client\Application\Command\ArchiveClient\ArchiveClient;
use App\Client\Application\Command\ArchiveClient\ArchiveClientHandler;
use App\Client\Domain\Client;
use App\Client\Domain\Exception\ClientClinicMismatchException;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Client\Domain\ValueObject\ClientId;
use App\Client\Domain\ValueObject\ClientIdentity;
use App\Client\Domain\ValueObject\ClientStatus;
use App\Client\Domain\ValueObject\ContactLabel;
use App\Client\Domain\ValueObject\ContactMethod;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Bus\IntegrationEventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Application\Event\IntegrationEventPublisher;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\Event\IntegrationEventInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;

final class ArchiveClientHandlerTest extends TestCase
{
    public function testHandleArchivesClient(): void
    {
        $clinicId  = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $clientId  = ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $now       = new \DateTimeImmutable('2024-01-02 14:00:00');
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');

        $client = Client::reconstitute(
            $clientId,
            $clinicId,
            new ClientIdentity('John', 'Doe'),
            ClientStatus::ACTIVE,
            [ContactMethod::email(EmailAddress::fromString('john@example.com'), ContactLabel::WORK, true)],
            $createdAt,
            $createdAt
        );

        $command = new ArchiveClient(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            clientId: '01234567-89ab-cdef-0123-456789abcdef'
        );

        $clientRepository    = $this->createMock(ClientRepositoryInterface::class);
        $eventBus            = $this->createMock(EventBusInterface::class);
        $integrationEventBus = $this->createMock(IntegrationEventBusInterface::class);
        $clock               = $this->createMock(ClockInterface::class);

        $clientRepository->expects(self::once())
            ->method('get')
            ->with(
                self::callback(fn (ClinicId $id) => $id->equals($clinicId)),
                self::callback(fn (ClientId $id) => $id->equals($clientId))
            )
            ->willReturn($client)
        ;

        $clock->expects(self::once())
            ->method('now')
            ->willReturn($now)
        ;

        $clientRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Client $c): bool {
                return $c->isArchived();
            }))
        ;

        $eventBus->expects(self::once())
            ->method('publish')
            ->with([], self::isInstanceOf(DomainEventInterface::class))
        ;

        $integrationEventBus->expects(self::once())
            ->method('publish')
            ->with([], self::isInstanceOf(IntegrationEventInterface::class))
        ;

        $handler = new ArchiveClientHandler(
            $clientRepository,
            new DomainEventPublisher($eventBus),
            new IntegrationEventPublisher($integrationEventBus),
            $clock
        );
        $handler($command);
    }

    public function testHandleThrowsExceptionWhenClinicMismatch(): void
    {
        $correctClinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $clientId        = ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $createdAt       = new \DateTimeImmutable('2024-01-01 10:00:00');

        $client = Client::reconstitute(
            $clientId,
            $correctClinicId,
            new ClientIdentity('John', 'Doe'),
            ClientStatus::ACTIVE,
            [ContactMethod::email(EmailAddress::fromString('john@example.com'), ContactLabel::WORK, true)],
            $createdAt,
            $createdAt
        );

        $command = new ArchiveClient(
            clinicId: 'ffffffff-ffff-ffff-ffff-ffffffffffff',
            clientId: '01234567-89ab-cdef-0123-456789abcdef'
        );

        $clientRepository    = $this->createMock(ClientRepositoryInterface::class);
        $eventBus            = $this->createStub(EventBusInterface::class);
        $integrationEventBus = $this->createStub(IntegrationEventBusInterface::class);
        $clock               = $this->createStub(ClockInterface::class);

        $clientRepository->expects(self::once())
            ->method('get')
            ->willReturn($client)
        ;

        $this->expectException(ClientClinicMismatchException::class);

        $handler = new ArchiveClientHandler(
            $clientRepository,
            new DomainEventPublisher($eventBus),
            new IntegrationEventPublisher($integrationEventBus),
            $clock
        );
        $handler($command);
    }
}
