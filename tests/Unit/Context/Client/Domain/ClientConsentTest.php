<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Domain;

use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Event\ClientCommunicationPreferencesUpdated;
use App\Context\Client\Domain\Exception\ClientArchivedCannotBeModifiedException;
use App\Context\Client\Domain\Exception\ConsentChannelRequiredException;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\CommunicationPreferences;
use App\Context\Client\Domain\ValueObject\Consent;
use App\Context\Client\Domain\ValueObject\ConsentActor;
use App\Context\Client\Domain\ValueObject\ConsentActorType;
use App\Context\Client\Domain\ValueObject\ConsentChangeAction;
use App\Context\Client\Domain\ValueObject\ConsentHistoryEntry;
use App\Context\Client\Domain\ValueObject\ConsentKind;
use App\Context\Client\Domain\ValueObject\ConsentSource;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Shared\Domain\ValueObject\EmailAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * S5a, S5b, S5c: the per-kind consent, its history rows with source and
 * author, and the archived client that keeps its consents untouched.
 */
final class ClientConsentTest extends TestCase
{
    private const string CLIENT_ID = '0192a000-0000-7000-8000-0000000c0501';
    private const string CLINIC_ID = '0192a000-0000-7000-8000-0000000c05c1';
    private const string USER_ID   = '0192a000-0000-7000-8000-0000000c05a1';

    public function testOnlyMarketingAndTheRemindersAreOptional(): void
    {
        self::assertSame([ConsentKind::MARKETING, ConsentKind::APPOINTMENT_REMINDERS, ConsentKind::VACCINATION_REMINDERS], ConsentKind::OPTIONAL);
        self::assertTrue(ConsentKind::MARKETING->isOptional());
        self::assertTrue(ConsentKind::APPOINTMENT_REMINDERS->isOptional());
        self::assertTrue(ConsentKind::VACCINATION_REMINDERS->isOptional());
        self::assertFalse(ConsentKind::BILLING->isOptional());
        self::assertFalse(ConsentKind::CLINICAL_DOCUMENTS->isOptional());
    }

    /** AC 54: narrowing marketing to SMS withdraws e-mail only, signed by the Portal user. */
    public function testSetConsentRecordsOneRowPerChannelChanged(): void
    {
        $client = $this->client(consents: [
            new Consent(ConsentKind::MARKETING, [ContactChannel::SMS, ContactChannel::EMAIL], new \DateTimeImmutable('2025-01-01')),
            new Consent(ConsentKind::BILLING, [ContactChannel::EMAIL], new \DateTimeImmutable('2025-01-01')),
        ]);
        $now   = new \DateTimeImmutable('2026-10-01 08:00:00');
        $actor = new ConsentActor(ConsentActorType::PORTAL_USER, self::USER_ID, ' Hélène Lambert ');

        $client->setConsent(ConsentKind::MARKETING, [ContactChannel::SMS], $now, ConsentSource::PORTAL, $actor, 'v2');

        self::assertSame([ContactChannel::SMS], $client->communicationPreferences()->consentFor(ConsentKind::MARKETING)?->channels);
        self::assertSame([ContactChannel::EMAIL], $client->communicationPreferences()->consentFor(ConsentKind::BILLING)?->channels);
        self::assertSame(ContactChannel::SMS, $client->communicationPreferences()->preferredChannel);
        self::assertSame($now, $client->updatedAt());

        $history = $client->pullConsentHistory();
        self::assertCount(1, $history);
        self::assertSame(self::CLIENT_ID, $history[0]->clientId->toString());
        self::assertSame(self::CLINIC_ID, $history[0]->clinicId->toString());
        self::assertSame([ConsentKind::MARKETING, ContactChannel::EMAIL, ConsentChangeAction::WITHDRAWN], [$history[0]->change->kind, $history[0]->change->channel, $history[0]->change->action]);
        self::assertSame($now, $history[0]->at);
        self::assertSame(ConsentSource::PORTAL, $history[0]->source);
        self::assertSame([ConsentActorType::PORTAL_USER, self::USER_ID, 'Hélène Lambert'], [$history[0]->actor->type, $history[0]->actor->id, $history[0]->actor->label]);
        self::assertSame('v2', $history[0]->wordingVersion);
        self::assertSame([], $client->pullConsentHistory(), 'pulled once');

        $events = $client->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClientCommunicationPreferencesUpdated::class, $events[0]);
        self::assertSame('sms', $events[0]->payload()['preferredChannel']);
    }

    public function testAnOptionalKindWithoutChannelIsWithdrawn(): void
    {
        $client = $this->client(consents: [new Consent(ConsentKind::VACCINATION_REMINDERS, [ContactChannel::SMS], new \DateTimeImmutable('2025-01-01'))]);

        $client->setConsent(ConsentKind::VACCINATION_REMINDERS, [], new \DateTimeImmutable(), ConsentSource::PORTAL, $this->staff());

        self::assertNull($client->communicationPreferences()->consentFor(ConsentKind::VACCINATION_REMINDERS));
        self::assertSame(ConsentChangeAction::WITHDRAWN, $client->pullConsentHistory()[0]->change->action);
    }

    /**
     * @return iterable<string, array{ConsentKind}>
     */
    public static function provideAChannelPreferenceKeepsAChannelCases(): iterable
    {
        yield 'billing' => [ConsentKind::BILLING];
        yield 'clinical documents' => [ConsentKind::CLINICAL_DOCUMENTS];
    }

    /** AC 204: billing and clinical documents only pick a channel. */
    #[DataProvider('provideAChannelPreferenceKeepsAChannelCases')]
    public function testAChannelPreferenceKeepsAChannel(ConsentKind $kind): void
    {
        $client = $this->client(consents: [new Consent($kind, [ContactChannel::EMAIL], new \DateTimeImmutable('2025-01-01'))]);

        try {
            $client->setConsent($kind, [], new \DateTimeImmutable(), ConsentSource::PORTAL, $this->staff());
            self::fail('A channel preference cannot be emptied.');
        } catch (ConsentChannelRequiredException $e) {
            self::assertStringContainsString($kind->value, $e->getMessage());
        }

        self::assertNotNull($client->communicationPreferences()->consentFor($kind));
        self::assertSame([], $client->pullConsentHistory());
    }

    public function testSettingTheSameChannelsChangesNothing(): void
    {
        $client = $this->client(consents: [new Consent(ConsentKind::MARKETING, [ContactChannel::SMS], new \DateTimeImmutable('2025-01-01'))]);
        $before = $client->updatedAt();

        $client->setConsent(ConsentKind::MARKETING, [ContactChannel::SMS], new \DateTimeImmutable('2026-10-01'), ConsentSource::PORTAL, $this->staff());

        self::assertSame([], $client->pullConsentHistory());
        self::assertSame([], $client->pullDomainEvents());
        self::assertSame($before, $client->updatedAt());
    }

    /** AC 58: no consent is added to an archived client, by any writer. */
    public function testAnArchivedClientIsRefusedAConsent(): void
    {
        $client = $this->client(ClientStatus::ARCHIVED);

        $this->expectException(ClientArchivedCannotBeModifiedException::class);

        $client->setConsent(ConsentKind::MARKETING, [ContactChannel::SMS], new \DateTimeImmutable(), ConsentSource::PORTAL, $this->staff());
    }

    /** AC 55, AC 203: a full replace is diffed the same way, with the clinic source and the staff member. */
    public function testUpdateCommunicationPreferencesRecordsTheDiff(): void
    {
        $client = $this->client(consents: [new Consent(ConsentKind::BILLING, [ContactChannel::EMAIL], new \DateTimeImmutable('2025-01-01'))]);
        $now    = new \DateTimeImmutable('2026-10-01 09:30:00');

        $client->updateCommunicationPreferences(ContactChannel::EMAIL, [
            ConsentKind::APPOINTMENT_REMINDERS->value => [ContactChannel::SMS, ContactChannel::EMAIL],
        ], $now, ConsentSource::CLINIC, $this->staff());

        $rows = array_map(
            static fn (ConsentHistoryEntry $entry): array => [$entry->change->kind->value, $entry->change->channel->value, $entry->change->action->value, $entry->source->value, $entry->actor->type->value, $entry->actor->label],
            $client->pullConsentHistory(),
        );
        self::assertSame([
            ['appointment_reminders', 'sms', 'granted', 'clinic', 'staff', 'Julie Martin'],
            ['appointment_reminders', 'email', 'granted', 'clinic', 'staff', 'Julie Martin'],
            ['billing', 'email', 'withdrawn', 'clinic', 'staff', 'Julie Martin'],
        ], $rows);
    }

    /** AC 57: archiving withdraws nothing and writes no history; unarchiving gives the consents back. */
    public function testArchivingKeepsTheConsents(): void
    {
        $consents = [
            new Consent(ConsentKind::MARKETING, [ContactChannel::EMAIL], new \DateTimeImmutable('2025-01-01')),
            new Consent(ConsentKind::APPOINTMENT_REMINDERS, [ContactChannel::SMS], new \DateTimeImmutable('2025-01-01')),
        ];
        $client = $this->client(consents: $consents);

        $client->archive(new \DateTimeImmutable('2026-10-01'));
        self::assertSame($consents, $client->communicationPreferences()->consents);

        $client->unarchive(new \DateTimeImmutable('2026-10-02'));
        self::assertSame($consents, $client->communicationPreferences()->consents);
        self::assertSame([], $client->pullConsentHistory());
    }

    public function testTheVersionReadIsKept(): void
    {
        self::assertSame(1, $this->client()->version());

        $createdAt = new \DateTimeImmutable('2019-03-12');
        $client    = Client::reconstitute(ClientId::fromString(self::CLIENT_ID), ClinicId::fromString(self::CLINIC_ID), new ClientIdentity('Hélène', 'Lambert'), ClientStatus::ACTIVE, [ContactMethod::email(EmailAddress::fromString('h@example.com'), ContactLabel::HOME, true)], $createdAt, $createdAt, version: 7);
        self::assertSame(7, $client->version());
    }

    /**
     * @return iterable<string, array{ConsentActorType, ?string, string, string}>
     */
    public static function provideAnInvalidAuthorIsRefusedCases(): iterable
    {
        yield 'an empty label' => [ConsentActorType::STAFF, null, '  ', 'label'];
        yield 'a label too long' => [ConsentActorType::STAFF, null, str_repeat('a', 256), 'label'];
        yield 'the system with an id' => [ConsentActorType::SYSTEM, self::USER_ID, 'Système', 'without an id'];
        yield 'an id that is no UUID' => [ConsentActorType::STAFF, '42', 'Julie', 'not a UUID'];
    }

    #[DataProvider('provideAnInvalidAuthorIsRefusedCases')]
    public function testAnInvalidAuthorIsRefused(ConsentActorType $type, ?string $id, string $label, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new ConsentActor($type, $id, $label);
    }

    private function staff(): ConsentActor
    {
        return new ConsentActor(ConsentActorType::STAFF, self::USER_ID, 'Julie Martin');
    }

    /**
     * @param list<Consent> $consents
     */
    private function client(ClientStatus $status = ClientStatus::ACTIVE, array $consents = []): Client
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
            communicationPreferences: new CommunicationPreferences(ContactChannel::SMS, $consents),
        );
    }
}
