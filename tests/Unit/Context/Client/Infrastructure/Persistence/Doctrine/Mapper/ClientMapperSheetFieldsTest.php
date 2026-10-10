<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Client\Domain\Client;
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
use App\Context\Client\Infrastructure\Persistence\Doctrine\Mapper\ClientMapper;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use PHPUnit\Framework\TestCase;

final class ClientMapperSheetFieldsTest extends TestCase
{
    public function testAuthorizedPersonAndPreferencesSurviveARoundTrip(): void
    {
        $mapper = new ClientMapper();
        $client = $this->client(
            new AuthorizedPerson(
                Civility::MISTER,
                'Marc',
                'Lambert',
                AuthorizedPersonRelationship::SPOUSE,
                PhoneNumber::fromString('+33688214509'),
                new \DateTimeImmutable('2022-03-14T10:00:00+00:00'),
            ),
            new CommunicationPreferences(ContactChannel::SMS, [
                new Consent(
                    ConsentKind::APPOINTMENT_REMINDERS,
                    [ContactChannel::SMS, ContactChannel::EMAIL],
                    new \DateTimeImmutable('2019-03-12T09:00:00+00:00'),
                ),
            ]),
        );

        $mapped = $mapper->toEntity($client);

        self::assertSame([
            'civility'     => 'mister',
            'firstName'    => 'Marc',
            'lastName'     => 'Lambert',
            'relationship' => 'spouse',
            'phoneNumber'  => '+33688214509',
            'authorizedAt' => '2022-03-14T10:00:00+00:00',
        ], $mapped['client']->getAuthorizedPerson());
        self::assertSame([
            'preferredChannel' => 'sms',
            'consents'         => [[
                'kind'      => 'appointment_reminders',
                'channels'  => ['sms', 'email'],
                'grantedAt' => '2019-03-12T09:00:00+00:00',
            ]],
        ], $mapped['client']->getCommunicationPreferences());

        $restored = $mapper->toDomain($mapped['client'], $mapped['contactMethods']);

        $person = $restored->authorizedPerson();
        self::assertNotNull($person);
        self::assertSame(Civility::MISTER, $person->civility);
        self::assertSame('Marc Lambert', $person->fullName());
        self::assertSame(AuthorizedPersonRelationship::SPOUSE, $person->relationship);
        self::assertSame('+33688214509', $person->phoneNumber?->toString());
        self::assertSame('2022-03-14T10:00:00+00:00', $person->authorizedAt->format(\DATE_ATOM));

        $preferences = $restored->communicationPreferences();
        self::assertSame(ContactChannel::SMS, $preferences->preferredChannel);

        $consent = $preferences->consentFor(ConsentKind::APPOINTMENT_REMINDERS);
        self::assertNotNull($consent);
        self::assertSame([ContactChannel::SMS, ContactChannel::EMAIL], $consent->channels);
        self::assertSame('2019-03-12T09:00:00+00:00', $consent->grantedAt->format(\DATE_ATOM));
    }

    public function testAPersonWithoutPhoneAndConsentsWithoutPreferredChannelSurviveARoundTrip(): void
    {
        $mapper = new ClientMapper();
        $client = $this->client(
            new AuthorizedPerson(
                Civility::UNSPECIFIED,
                'Julie',
                'Lambert',
                AuthorizedPersonRelationship::CHILD,
                null,
                new \DateTimeImmutable('2026-01-04T10:00:00+00:00'),
            ),
            new CommunicationPreferences(null, [
                new Consent(ConsentKind::MARKETING, [ContactChannel::EMAIL], new \DateTimeImmutable('2026-01-04')),
            ]),
        );

        $mapped   = $mapper->toEntity($client);
        $restored = $mapper->toDomain($mapped['client'], $mapped['contactMethods']);

        self::assertNull($restored->authorizedPerson()?->phoneNumber);
        self::assertNull($restored->communicationPreferences()->preferredChannel);
        self::assertNotNull($restored->communicationPreferences()->consentFor(ConsentKind::MARKETING));
    }

    public function testNothingIsStoredForAClientWithoutAuthorizedPersonNorPreferences(): void
    {
        $mapper = new ClientMapper();
        $mapped = $mapper->toEntity($this->client(null, null));

        self::assertNull($mapped['client']->getAuthorizedPerson());
        self::assertNull($mapped['client']->getCommunicationPreferences());

        $restored = $mapper->toDomain($mapped['client'], $mapped['contactMethods']);

        self::assertNull($restored->authorizedPerson());
        self::assertSame([], $restored->communicationPreferences()->consents);
        self::assertNull($restored->communicationPreferences()->preferredChannel);
    }

    private function client(?AuthorizedPerson $person, ?CommunicationPreferences $preferences): Client
    {
        $createdAt = new \DateTimeImmutable('2019-03-12 10:00:00');

        return Client::reconstitute(
            ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0'),
            new ClientIdentity('Hélène', 'Lambert'),
            ClientStatus::ACTIVE,
            [ContactMethod::email(EmailAddress::fromString('h.lambert@example.com'), ContactLabel::HOME, true)],
            $createdAt,
            $createdAt,
            null,
            $person,
            $preferences,
        );
    }
}
