<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Domain;

use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Event\ClientAuthorizedPersonChanged;
use App\Context\Client\Domain\Event\ClientCommunicationPreferencesUpdated;
use App\Context\Client\Domain\Exception\ClientArchivedCannotBeModifiedException;
use App\Context\Client\Domain\ValueObject\AuthorizedPerson;
use App\Context\Client\Domain\ValueObject\AuthorizedPersonRelationship;
use App\Context\Client\Domain\ValueObject\Civility;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\CommunicationPreferences;
use App\Context\Client\Domain\ValueObject\Consent;
use App\Context\Client\Domain\ValueObject\ConsentKind;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use PHPUnit\Framework\TestCase;

final class ClientAuthorizedPersonAndPreferencesTest extends TestCase
{
    private const string CLIENT_ID = '01234567-89ab-cdef-0123-456789abcdef';
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';

    public function testANewClientHasNoAuthorizedPersonAndNoPreferences(): void
    {
        $client = Client::create(
            ClientId::fromString(self::CLIENT_ID),
            ClinicId::fromString(self::CLINIC_ID),
            new ClientIdentity('Hélène', 'Lambert'),
            [ContactMethod::email(EmailAddress::fromString('h.lambert@example.com'), ContactLabel::HOME, true)],
            new \DateTimeImmutable('2019-03-12'),
        );

        self::assertNull($client->authorizedPerson());
        self::assertNull($client->communicationPreferences()->preferredChannel);
        self::assertSame([], $client->communicationPreferences()->consents);
    }

    public function testReconstituteRestoresAuthorizedPersonAndPreferences(): void
    {
        $person      = $this->marc(new \DateTimeImmutable('2022-03-14'));
        $preferences = new CommunicationPreferences(ContactChannel::SMS, [
            new Consent(ConsentKind::MARKETING, [ContactChannel::EMAIL], new \DateTimeImmutable('2024-02-08')),
        ]);

        $client = $this->client(ClientStatus::ACTIVE, $person, $preferences);

        self::assertSame($person, $client->authorizedPerson());
        self::assertSame($preferences, $client->communicationPreferences());
        self::assertSame([], $client->pullDomainEvents());
    }

    public function testChangeAuthorizedPersonDesignatesSomeone(): void
    {
        $client = $this->client();
        $now    = new \DateTimeImmutable('2026-09-30 09:00:00');
        $person = $this->marc($now);

        $client->changeAuthorizedPerson($person, $now);

        self::assertSame($person, $client->authorizedPerson());
        self::assertSame($now, $client->updatedAt());

        $events = $client->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClientAuthorizedPersonChanged::class, $events[0]);
        self::assertSame(self::CLIENT_ID, $events[0]->aggregateId());
        self::assertSame([
            'clientId'         => self::CLIENT_ID,
            'clinicId'         => self::CLINIC_ID,
            'authorizedPerson' => [
                'civility'     => 'mister',
                'firstName'    => 'Marc',
                'lastName'     => 'Lambert',
                'relationship' => 'spouse',
                'phoneNumber'  => '+33688214509',
                'authorizedAt' => $now->format(\DATE_ATOM),
            ],
        ], $events[0]->payload());
    }

    public function testDesignatingTheSamePersonAgainKeepsTheOriginalDate(): void
    {
        $original = new \DateTimeImmutable('2022-03-14 10:00:00');
        $client   = $this->client(ClientStatus::ACTIVE, $this->marc($original));
        $now      = new \DateTimeImmutable('2026-09-30 09:00:00');

        $client->changeAuthorizedPerson(new AuthorizedPerson(
            Civility::MISTER,
            'Marc',
            'Lambert',
            AuthorizedPersonRelationship::PARENT,
            null,
            $now,
        ), $now);

        $person = $client->authorizedPerson();
        self::assertNotNull($person);
        self::assertSame($original, $person->authorizedAt);
        self::assertSame(AuthorizedPersonRelationship::PARENT, $person->relationship);
        self::assertNull($person->phoneNumber);
    }

    public function testDesignatingSomeoneElseStartsANewAuthorization(): void
    {
        $client = $this->client(ClientStatus::ACTIVE, $this->marc(new \DateTimeImmutable('2022-03-14')));
        $now    = new \DateTimeImmutable('2026-09-30 09:00:00');

        $client->changeAuthorizedPerson(new AuthorizedPerson(
            Civility::MADAM,
            'Julie',
            'Lambert',
            AuthorizedPersonRelationship::CHILD,
            null,
            $now,
        ), $now);

        self::assertSame($now, $client->authorizedPerson()?->authorizedAt);
    }

    public function testChangeAuthorizedPersonToNullWithdrawsTheAuthorization(): void
    {
        $client = $this->client(ClientStatus::ACTIVE, $this->marc(new \DateTimeImmutable('2022-03-14')));

        $client->changeAuthorizedPerson(null, new \DateTimeImmutable('2026-09-30'));

        self::assertNull($client->authorizedPerson());

        $events = $client->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertNull($events[0]->payload()['authorizedPerson']);
    }

    public function testChangeAuthorizedPersonIsRefusedOnAnArchivedClient(): void
    {
        $client = $this->client(ClientStatus::ARCHIVED);

        $this->expectException(ClientArchivedCannotBeModifiedException::class);

        $client->changeAuthorizedPerson(null, new \DateTimeImmutable());
    }

    public function testUpdateCommunicationPreferencesRecordsConsents(): void
    {
        $client = $this->client();
        $now    = new \DateTimeImmutable('2026-09-30 09:00:00');

        $client->updateCommunicationPreferences(
            ContactChannel::SMS,
            [ConsentKind::APPOINTMENT_REMINDERS->value => [ContactChannel::SMS, ContactChannel::EMAIL]],
            $now,
        );

        $preferences = $client->communicationPreferences();
        self::assertSame(ContactChannel::SMS, $preferences->preferredChannel);
        self::assertNotNull($preferences->consentFor(ConsentKind::APPOINTMENT_REMINDERS));
        self::assertSame($now, $client->updatedAt());

        $events = $client->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClientCommunicationPreferencesUpdated::class, $events[0]);
        self::assertSame(self::CLIENT_ID, $events[0]->aggregateId());
        self::assertSame([
            'clientId'         => self::CLIENT_ID,
            'clinicId'         => self::CLINIC_ID,
            'preferredChannel' => 'sms',
            'consents'         => [[
                'kind'      => 'appointment_reminders',
                'channels'  => ['sms', 'email'],
                'grantedAt' => $now->format(\DATE_ATOM),
            ]],
        ], $events[0]->payload());
    }

    public function testUpdateCommunicationPreferencesCanClearEverything(): void
    {
        $client = $this->client(ClientStatus::ACTIVE, null, new CommunicationPreferences(ContactChannel::SMS, [
            new Consent(ConsentKind::MARKETING, [ContactChannel::EMAIL], new \DateTimeImmutable('2024-02-08')),
        ]));

        $client->updateCommunicationPreferences(null, [], new \DateTimeImmutable('2026-09-30'));

        self::assertNull($client->communicationPreferences()->preferredChannel);
        self::assertSame([], $client->communicationPreferences()->consents);
        self::assertNull($client->pullDomainEvents()[0]->payload()['preferredChannel']);
    }

    public function testUpdateCommunicationPreferencesIsRefusedOnAnArchivedClient(): void
    {
        $client = $this->client(ClientStatus::ARCHIVED);

        $this->expectException(ClientArchivedCannotBeModifiedException::class);

        $client->updateCommunicationPreferences(null, [], new \DateTimeImmutable());
    }

    private function marc(\DateTimeImmutable $authorizedAt): AuthorizedPerson
    {
        return new AuthorizedPerson(
            Civility::MISTER,
            'Marc',
            'Lambert',
            AuthorizedPersonRelationship::SPOUSE,
            PhoneNumber::fromString('+33688214509'),
            $authorizedAt,
        );
    }

    private function client(
        ClientStatus $status = ClientStatus::ACTIVE,
        ?AuthorizedPerson $person = null,
        ?CommunicationPreferences $preferences = null,
    ): Client {
        $createdAt = new \DateTimeImmutable('2019-03-12 10:00:00');

        return Client::reconstitute(
            ClientId::fromString(self::CLIENT_ID),
            ClinicId::fromString(self::CLINIC_ID),
            new ClientIdentity('Hélène', 'Lambert'),
            $status,
            [ContactMethod::email(EmailAddress::fromString('h.lambert@example.com'), ContactLabel::HOME, true)],
            $createdAt,
            $createdAt,
            null,
            $person,
            $preferences,
        );
    }
}
