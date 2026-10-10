<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Command\LogClientCommunication;

use App\Context\Client\Application\Command\LogClientCommunication\LogClientCommunication;
use App\Context\Client\Application\Command\LogClientCommunication\LogClientCommunicationHandler;
use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\ClientCommunication;
use App\Context\Client\Domain\Exception\ClientNotFoundException;
use App\Context\Client\Domain\Repository\ClientCommunicationRepositoryInterface;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\CommunicationDirection;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;

final class LogClientCommunicationHandlerTest extends TestCase
{
    private const string COMMUNICATION_ID = '0192a000-0000-7000-8000-000000000002';
    private const string CLIENT_ID        = '01234567-89ab-cdef-0123-456789abcdef';
    private const string CLINIC_ID        = '12345678-9abc-def0-1234-56789abcdef0';

    public function testLogsTheExchangeForAClientOfTheClinic(): void
    {
        $now = new \DateTimeImmutable('2026-03-02 09:12:00');

        $clients = $this->createMock(ClientRepositoryInterface::class);
        $clients->expects(self::once())->method('get')->willReturn($this->client());

        $saved          = null;
        $communications = $this->createMock(ClientCommunicationRepositoryInterface::class);
        $communications->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (ClientCommunication $communication) use (&$saved): void {
                $saved = $communication;
            })
        ;

        $uuids = $this->createStub(UuidGeneratorInterface::class);
        $uuids->method('generate')->willReturn(self::COMMUNICATION_ID);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $handler = new LogClientCommunicationHandler(
            $clients,
            $communications,
            $uuids,
            new DomainEventPublisher($eventBus),
            $clock,
        );

        $communicationId = $handler(new LogClientCommunication(
            clinicId: self::CLINIC_ID,
            clientId: self::CLIENT_ID,
            channel: 'phone',
            direction: 'inbound',
            subject: 'Appel entrant',
            body: 'Prise de rendez-vous.',
            authorName: 'Camille',
        ));

        self::assertSame(self::COMMUNICATION_ID, $communicationId);
        self::assertInstanceOf(ClientCommunication::class, $saved);
        self::assertSame(self::COMMUNICATION_ID, $saved->id()->toString());
        self::assertSame(self::CLIENT_ID, $saved->clientId()->toString());
        self::assertSame(ContactChannel::PHONE, $saved->channel());
        self::assertSame(CommunicationDirection::INBOUND, $saved->direction());
        self::assertSame('Appel entrant', $saved->subject());
        self::assertSame('Prise de rendez-vous.', $saved->body());
        self::assertSame('Camille', $saved->authorName());
        self::assertSame($now, $saved->occurredAt());
    }

    public function testRefusesAClientThatIsNotInTheClinic(): void
    {
        $clients = $this->createStub(ClientRepositoryInterface::class);
        $clients->method('get')->willThrowException(new ClientNotFoundException(self::CLIENT_ID));

        $communications = $this->createMock(ClientCommunicationRepositoryInterface::class);
        $communications->expects(self::never())->method('save');

        $handler = new LogClientCommunicationHandler(
            $clients,
            $communications,
            $this->createStub(UuidGeneratorInterface::class),
            new DomainEventPublisher($this->createStub(EventBusInterface::class)),
            $this->createStub(ClockInterface::class),
        );

        $this->expectException(ClientNotFoundException::class);

        $handler(new LogClientCommunication(self::CLINIC_ID, self::CLIENT_ID, 'sms', 'outbound', 'Rappel', null, 'Camille'));
    }

    private function client(): Client
    {
        $createdAt = new \DateTimeImmutable('2019-03-12 10:00:00');

        return Client::reconstitute(
            ClientId::fromString(self::CLIENT_ID),
            ClinicId::fromString(self::CLINIC_ID),
            new ClientIdentity('Hélène', 'Lambert'),
            ClientStatus::ACTIVE,
            [ContactMethod::email(EmailAddress::fromString('h.lambert@example.com'), ContactLabel::HOME, true)],
            $createdAt,
            $createdAt,
        );
    }
}
