<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Command\UpdateClientCommunicationPreferences;

use App\Context\Client\Application\Command\UpdateClientCommunicationPreferences\UpdateClientCommunicationPreferences;
use App\Context\Client\Application\Command\UpdateClientCommunicationPreferences\UpdateClientCommunicationPreferencesHandler;
use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Exception\StaleConsentsException;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ConsentActorType;
use App\Context\Client\Domain\ValueObject\ConsentKind;
use App\Context\Client\Domain\ValueObject\ConsentSource;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Shared\Application\Bus\CommandInterface;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;

final class UpdateClientCommunicationPreferencesHandlerTest extends TestCase
{
    private const string CLIENT_ID = '01234567-89ab-cdef-0123-456789abcdef';
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';
    private const string USER_ID   = '0192a000-0000-7000-8000-0000000c05a1';

    public function testRecordsThePreferredChannelAndTheConsents(): void
    {
        $now    = new \DateTimeImmutable('2026-09-30 09:00:00');
        $client = $this->client();

        $this->handler($client, $now, 1)($this->command('phone', [
            'appointment_reminders' => ['sms', 'email'],
            'marketing'             => [],
        ], expectedVersion: 3));

        $preferences = $client->communicationPreferences();
        self::assertSame(ContactChannel::PHONE, $preferences->preferredChannel);
        self::assertCount(1, $preferences->consents);

        $consent = $preferences->consentFor(ConsentKind::APPOINTMENT_REMINDERS);
        self::assertNotNull($consent);
        self::assertSame([ContactChannel::SMS, ContactChannel::EMAIL], $consent->channels);
        self::assertSame($now, $consent->grantedAt);
    }

    /** AC 55: the history rows carry the source and the staff member given. */
    public function testTheHistoryIsSignedBySourceAndAuthor(): void
    {
        $client = $this->client();

        $this->handler($client, new \DateTimeImmutable('2026-09-30'), 1)($this->command(null, ['marketing' => ['email']]));

        $history = $client->pullConsentHistory();
        self::assertCount(1, $history);
        self::assertSame(ConsentSource::CLINIC, $history[0]->source);
        self::assertSame([ConsentActorType::STAFF, self::USER_ID, 'Julie Martin'], [$history[0]->actor->type, $history[0]->actor->id, $history[0]->actor->label]);
    }

    public function testClearsThePreferredChannel(): void
    {
        $client = $this->client();

        $this->handler($client, new \DateTimeImmutable('2026-09-30'), 1)($this->command(null, []));

        self::assertNull($client->communicationPreferences()->preferredChannel);
        self::assertSame([], $client->communicationPreferences()->consents);
    }

    /** AC 202: a form rendered before another change of the client is refused, nothing saved. */
    public function testAStaleFormIsRefused(): void
    {
        $client = $this->client();

        try {
            $this->handler($client, new \DateTimeImmutable(), 0)($this->command(null, ['marketing' => ['sms']], expectedVersion: 2));
            self::fail('A stale save must be refused.');
        } catch (StaleConsentsException $e) {
            self::assertStringContainsString('version 2, now 3', $e->getMessage());
        }

        self::assertSame([], $client->communicationPreferences()->consents);
    }

    public function testRejectsAnUnknownConsentKind(): void
    {
        $this->expectException(\ValueError::class);

        $this->handler($this->client(), new \DateTimeImmutable(), 0)($this->command(null, ['telepathy' => ['sms']]));
    }

    public function testIsACommand(): void
    {
        self::assertInstanceOf(CommandInterface::class, $this->command(null, []));
    }

    /**
     * @param array<string, list<string>> $consents
     */
    private function command(?string $preferred, array $consents, ?int $expectedVersion = null): UpdateClientCommunicationPreferences
    {
        return new UpdateClientCommunicationPreferences(
            clinicId: self::CLINIC_ID,
            clientId: self::CLIENT_ID,
            preferredChannel: $preferred,
            channelsByConsentKind: $consents,
            source: 'clinic',
            actorType: 'staff',
            actorId: self::USER_ID,
            authorLabel: 'Julie Martin',
            expectedVersion: $expectedVersion,
        );
    }

    private function handler(
        Client $client,
        \DateTimeImmutable $now,
        int $saves,
    ): UpdateClientCommunicationPreferencesHandler {
        $clients = $this->createMock(ClientRepositoryInterface::class);
        $clients->expects(self::once())
            ->method('getForUpdate')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
                self::callback(static fn (ClientId $id): bool => self::CLIENT_ID === $id->toString()),
            )
            ->willReturn($client)
        ;
        $clients->expects(self::never())->method('get');
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
            version: 3,
        );
    }
}
