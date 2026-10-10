<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Command\UpdateClientCommunicationPreferences;

use App\Context\Client\Application\Command\UpdateClientCommunicationPreferences\UpdateClientCommunicationPreferences;
use App\Context\Client\Application\Command\UpdateClientCommunicationPreferences\UpdateClientCommunicationPreferencesHandler;
use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ConsentKind;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;

final class UpdateClientCommunicationPreferencesHandlerTest extends TestCase
{
    private const string CLIENT_ID = '01234567-89ab-cdef-0123-456789abcdef';
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';

    public function testRecordsThePreferredChannelAndTheConsents(): void
    {
        $now    = new \DateTimeImmutable('2026-09-30 09:00:00');
        $client = $this->client();

        $this->handler($client, $now, 1)(new UpdateClientCommunicationPreferences(
            clinicId: self::CLINIC_ID,
            clientId: self::CLIENT_ID,
            preferredChannel: 'phone',
            channelsByConsentKind: [
                'appointment_reminders' => ['sms', 'email'],
                'marketing'             => [],
            ],
        ));

        $preferences = $client->communicationPreferences();
        self::assertSame(ContactChannel::PHONE, $preferences->preferredChannel);
        self::assertCount(1, $preferences->consents);

        $consent = $preferences->consentFor(ConsentKind::APPOINTMENT_REMINDERS);
        self::assertNotNull($consent);
        self::assertSame([ContactChannel::SMS, ContactChannel::EMAIL], $consent->channels);
        self::assertSame($now, $consent->grantedAt);
    }

    public function testClearsThePreferredChannel(): void
    {
        $client = $this->client();

        $this->handler($client, new \DateTimeImmutable('2026-09-30'), 1)(new UpdateClientCommunicationPreferences(
            clinicId: self::CLINIC_ID,
            clientId: self::CLIENT_ID,
            preferredChannel: null,
            channelsByConsentKind: [],
        ));

        self::assertNull($client->communicationPreferences()->preferredChannel);
        self::assertSame([], $client->communicationPreferences()->consents);
    }

    public function testRejectsAnUnknownConsentKind(): void
    {
        $this->expectException(\ValueError::class);

        $this->handler($this->client(), new \DateTimeImmutable(), 0)(new UpdateClientCommunicationPreferences(
            clinicId: self::CLINIC_ID,
            clientId: self::CLIENT_ID,
            preferredChannel: null,
            channelsByConsentKind: ['telepathy' => ['sms']],
        ));
    }

    private function handler(
        Client $client,
        \DateTimeImmutable $now,
        int $saves,
    ): UpdateClientCommunicationPreferencesHandler {
        $clients = $this->createMock(ClientRepositoryInterface::class);
        $clients->expects(self::once())
            ->method('get')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
                self::callback(static fn (ClientId $id): bool => self::CLIENT_ID === $id->toString()),
            )
            ->willReturn($client)
        ;
        $clients->expects(self::exactly($saves))->method('save')->with($client);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::exactly($saves))->method('publish');

        return new UpdateClientCommunicationPreferencesHandler($clients, new DomainEventPublisher($eventBus), $clock);
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
