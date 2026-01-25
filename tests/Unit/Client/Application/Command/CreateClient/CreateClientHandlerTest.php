<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Application\Command\CreateClient;

use App\Client\Application\Command\CreateClient\ContactMethodDto;
use App\Client\Application\Command\CreateClient\CreateClient;
use App\Client\Application\Command\CreateClient\CreateClientHandler;
use App\Client\Domain\Client;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Client\Domain\ValueObject\ClientId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class CreateClientHandlerTest extends TestCase
{
    public function testHandleCreatesClientAndReturnsId(): void
    {
        $clientId       = ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $now            = new \DateTimeImmutable('2024-01-01 10:00:00');
        $contactMethods = [
            new ContactMethodDto('email', 'work', 'john@example.com', true),
        ];

        $command = new CreateClient(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            firstName: 'John',
            lastName: 'Doe',
            contactMethods: $contactMethods
        );

        $clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $eventBus         = $this->createMock(EventBusInterface::class);
        $clock            = $this->createMock(ClockInterface::class);

        $clientRepository->expects(self::once())
            ->method('nextId')
            ->willReturn($clientId)
        ;

        $clock->expects(self::once())
            ->method('now')
            ->willReturn($now)
        ;

        $clientRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Client $client) use ($clientId): bool {
                return $client->id()->equals($clientId)
                    && 'John' === $client->identity()->firstName
                    && 'Doe' === $client->identity()->lastName;
            }))
        ;

        $eventBus->expects(self::once())
            ->method('publish')
            ->with([], self::isInstanceOf(DomainEventInterface::class))
        ;

        $handler = new CreateClientHandler($clientRepository, new DomainEventPublisher($eventBus), $clock);
        $result  = $handler($command);

        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $result);
    }

    public function testHandleCreatesClientWithPhoneContactMethod(): void
    {
        $clientId       = ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $now            = new \DateTimeImmutable('2024-01-01 10:00:00');
        $contactMethods = [
            new ContactMethodDto('phone', 'mobile', '+33612345678', true),
        ];

        $command = new CreateClient(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            firstName: 'John',
            lastName: 'Doe',
            contactMethods: $contactMethods
        );

        $clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $eventBus         = $this->createMock(EventBusInterface::class);
        $clock            = $this->createStub(ClockInterface::class);

        $clientRepository->method('nextId')->willReturn($clientId);
        $clock->method('now')->willReturn($now);
        $clientRepository->expects(self::once())->method('save');
        $eventBus->expects(self::once())->method('publish');

        $handler = new CreateClientHandler($clientRepository, new DomainEventPublisher($eventBus), $clock);
        $result  = $handler($command);

        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $result);
    }

    public function testHandleCreatesClientWithMultipleContactMethods(): void
    {
        $clientId       = ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $now            = new \DateTimeImmutable('2024-01-01 10:00:00');
        $contactMethods = [
            new ContactMethodDto('email', 'work', 'john@example.com', true),
            new ContactMethodDto('phone', 'mobile', '+33612345678', true),
            new ContactMethodDto('email', 'home', 'john.doe@personal.com', false),
        ];

        $command = new CreateClient(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            firstName: 'John',
            lastName: 'Doe',
            contactMethods: $contactMethods
        );

        $clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $eventBus         = $this->createMock(EventBusInterface::class);
        $clock            = $this->createStub(ClockInterface::class);

        $clientRepository->method('nextId')->willReturn($clientId);
        $clock->method('now')->willReturn($now);

        $clientRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Client $client): bool {
                return 3 === \count($client->contactMethods());
            }))
        ;

        $eventBus->expects(self::once())->method('publish');

        $handler = new CreateClientHandler($clientRepository, new DomainEventPublisher($eventBus), $clock);
        $result  = $handler($command);

        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $result);
    }
}
