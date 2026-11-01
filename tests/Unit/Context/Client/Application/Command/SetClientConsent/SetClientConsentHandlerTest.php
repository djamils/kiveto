<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Command\SetClientConsent;

use App\Context\Client\Application\Command\SetClientConsent\SetClientConsent;
use App\Context\Client\Application\Command\SetClientConsent\SetClientConsentHandler;
use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Exception\ClientArchivedCannotBeModifiedException;
use App\Context\Client\Domain\Exception\ConsentChannelRequiredException;
use App\Context\Client\Domain\Exception\InvalidConsentChannelException;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\CommunicationPreferences;
use App\Context\Client\Domain\ValueObject\Consent;
use App\Context\Client\Domain\ValueObject\ConsentActorType;
use App\Context\Client\Domain\ValueObject\ConsentChangeAction;
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * S5a, S5b: one kind changed from the Portal, the client locked while it
 * is; channels other than sms and email and an emptied channel preference
 * are refused.
 */
final class SetClientConsentHandlerTest extends TestCase
{
    private const string CLIENT_ID = '0192a000-0000-7000-8000-0000000c0601';
    private const string CLINIC_ID = '0192a000-0000-7000-8000-0000000c06c1';
    private const string USER_ID   = '0192a000-0000-7000-8000-0000000c06a1';

    /** AC 54 */
    public function testNarrowsOneKindAndRecordsTheWithdrawal(): void
    {
        $client = $this->client();
        $now    = new \DateTimeImmutable('2026-10-01 10:00:00');

        $this->handler($client, $now, 1)($this->command('marketing', ['sms']));

        $preferences = $client->communicationPreferences();
        self::assertSame([ContactChannel::SMS], $preferences->consentFor(ConsentKind::MARKETING)?->channels);
        self::assertSame([ContactChannel::EMAIL], $preferences->consentFor(ConsentKind::BILLING)?->channels, 'the other kinds are untouched');

        $history = $client->pullConsentHistory();
        self::assertCount(1, $history);
        self::assertSame([ConsentKind::MARKETING, ContactChannel::EMAIL, ConsentChangeAction::WITHDRAWN], [$history[0]->change->kind, $history[0]->change->channel, $history[0]->change->action]);
        self::assertSame([ConsentSource::PORTAL, ConsentActorType::PORTAL_USER, self::USER_ID, 'Hélène Lambert', 'v1'], [$history[0]->source, $history[0]->actor->type, $history[0]->actor->id, $history[0]->actor->label, $history[0]->wordingVersion]);
        self::assertSame($now, $history[0]->at);
    }

    /**
     * @return iterable<string, array{string, list<string>, class-string<\Throwable>}>
     */
    public static function provideARefusedChangeSavesNothingCases(): iterable
    {
        // AC 56
        yield 'a phone channel' => ['marketing', ['phone'], InvalidConsentChannelException::class];
        yield 'an unknown channel' => ['marketing', ['push'], InvalidConsentChannelException::class];
        yield 'an unknown kind' => ['telepathy', ['sms'], \InvalidArgumentException::class];
        // AC 204
        yield 'billing emptied' => ['billing', [], ConsentChannelRequiredException::class];
    }

    /**
     * @param list<string>             $channels
     * @param class-string<\Throwable> $exception
     */
    #[DataProvider('provideARefusedChangeSavesNothingCases')]
    public function testARefusedChangeSavesNothing(string $kind, array $channels, string $exception): void
    {
        $this->expectException($exception);

        $this->handler($this->client(), new \DateTimeImmutable(), 0, ConsentChannelRequiredException::class === $exception ? 1 : 0)($this->command($kind, $channels));
    }

    /** AC 58 */
    public function testAnArchivedClientIsRefused(): void
    {
        $this->expectException(ClientArchivedCannotBeModifiedException::class);

        $this->handler($this->client(ClientStatus::ARCHIVED), new \DateTimeImmutable(), 0)($this->command('marketing', ['sms', 'email']));
    }

    public function testIsACommand(): void
    {
        self::assertInstanceOf(CommandInterface::class, $this->command('marketing', []));
    }

    /**
     * @param list<string> $channels
     */
    private function command(string $kind, array $channels): SetClientConsent
    {
        return new SetClientConsent(
            clinicId: self::CLINIC_ID,
            clientId: self::CLIENT_ID,
            kind: $kind,
            channels: $channels,
            source: 'portal',
            actorType: 'portal_user',
            actorId: self::USER_ID,
            authorLabel: 'Hélène Lambert',
            wordingVersion: 'v1',
        );
    }

    private function handler(Client $client, \DateTimeImmutable $now, int $saves, ?int $loads = null): SetClientConsentHandler
    {
        $clients = $this->createMock(ClientRepositoryInterface::class);
        $clients->expects(self::exactly($loads ?? ($saves > 0 || ClientStatus::ARCHIVED === $client->status() ? 1 : 0)))
            ->method('getForUpdate')
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

        return new SetClientConsentHandler($clients, new DomainEventPublisher($eventBus), $clock);
    }

    private function client(ClientStatus $status = ClientStatus::ACTIVE): Client
    {
        $createdAt = new \DateTimeImmutable('2019-03-12 10:00:00');

        return Client::reconstitute(
            ClientId::fromString(self::CLIENT_ID),
            ClinicId::fromString(self::CLINIC_ID),
            new ClientIdentity('Hélène', 'Lambert'),
            $status,
            [ContactMethod::email(EmailAddress::fromString('h.lambert@example.com'), ContactLabel::HOME, true)],
            $createdAt,
            $createdAt,
            communicationPreferences: new CommunicationPreferences(null, [
                new Consent(ConsentKind::MARKETING, [ContactChannel::SMS, ContactChannel::EMAIL], new \DateTimeImmutable('2025-01-01')),
                new Consent(ConsentKind::BILLING, [ContactChannel::EMAIL], new \DateTimeImmutable('2025-01-01')),
            ]),
        );
    }
}
