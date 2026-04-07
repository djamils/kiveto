<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Application\Command\UnarchiveClient;

use App\Client\Application\Command\UnarchiveClient\UnarchiveClient;
use App\Client\Application\Command\UnarchiveClient\UnarchiveClientHandler;
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
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;

final class UnarchiveClientHandlerTest extends TestCase
{
    public function testHandleUnarchivesClient(): void
    {
        $clinicId  = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $clientId  = ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $now       = new \DateTimeImmutable('2024-01-02 14:00:00');
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');

        $client = Client::reconstitute(
            $clientId,
            $clinicId,
            new ClientIdentity('John', 'Doe'),
            ClientStatus::ARCHIVED,
            [ContactMethod::email(EmailAddress::fromString('john@example.com'), ContactLabel::WORK, true)],
            $createdAt,
            $createdAt
        );

        $command = new UnarchiveClient(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            clientId: '01234567-89ab-cdef-0123-456789abcdef'
        );

        $clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $eventBus         = $this->createMock(EventBusInterface::class);
        $clock            = $this->createMock(ClockInterface::class);

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
                return !$c->isArchived();
            }))
        ;

        $eventBus->expects(self::once())
            ->method('publish')
            ->with([], self::isInstanceOf(DomainEventInterface::class))
        ;

        $handler = new UnarchiveClientHandler($clientRepository, new DomainEventPublisher($eventBus), $clock);
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
            ClientStatus::ARCHIVED,
            [ContactMethod::email(EmailAddress::fromString('john@example.com'), ContactLabel::WORK, true)],
            $createdAt,
            $createdAt
        );

        $command = new UnarchiveClient(
            clinicId: 'ffffffff-ffff-ffff-ffff-ffffffffffff',
            clientId: '01234567-89ab-cdef-0123-456789abcdef'
        );

        $clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $eventBus         = $this->createStub(EventBusInterface::class);
        $clock            = $this->createStub(ClockInterface::class);

        $clientRepository->expects(self::once())
            ->method('get')
            ->willReturn($client)
        ;

        $this->expectException(ClientClinicMismatchException::class);

        $handler = new UnarchiveClientHandler($clientRepository, new DomainEventPublisher($eventBus), $clock);
        $handler($command);
    }
}
