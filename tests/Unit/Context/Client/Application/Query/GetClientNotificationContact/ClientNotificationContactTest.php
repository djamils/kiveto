<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Query\GetClientNotificationContact;

use App\Context\Client\Application\Query\GetClientNotificationContact\ClientNotificationContact;
use PHPUnit\Framework\TestCase;

/**
 * The consent table of the notifications spec: a consent only chooses the
 * channel, except for the SMS appointment reminder it governs.
 */
final class ClientNotificationContactTest extends TestCase
{
    private const string CLIENT_ID = '01234567-89ab-cdef-0123-456789abcdef';

    public function testPicksThePrimaryEmailAndThePrimaryMobile(): void
    {
        $contact = ClientNotificationContact::compose(
            clientId: self::CLIENT_ID,
            firstName: 'Hélène',
            lastName: 'Lambert',
            legalName: null,
            civility: 'madam',
            archived: false,
            contactMethods: [
                self::method('email', 'work', 'h.lambert@travail.fr'),
                self::method('phone', 'mobile', '+33688214509'),
                self::method('email', 'home', 'h.lambert@example.com', true),
                self::method('phone', 'mobile', '+33611223344', true),
            ],
            preferredChannel: 'sms',
            consents: ['appointment_reminders' => ['sms']],
        );

        self::assertSame(self::CLIENT_ID, $contact->clientId);
        self::assertSame('Hélène Lambert', $contact->displayName);
        self::assertSame('Mme', $contact->civility);
        self::assertSame('Hélène', $contact->firstName);
        self::assertSame('Lambert', $contact->lastName);
        self::assertSame('h.lambert@example.com', $contact->email);
        self::assertSame('+33611223344', $contact->mobile);
        self::assertSame('sms', $contact->preferredChannel);
        self::assertSame(['appointment_reminders' => ['sms']], $contact->consents);
        self::assertFalse($contact->archived);
        self::assertSame('Mme Lambert', $contact->formalName());
    }

    public function testFallsBackOnTheFirstEmailAndTheFirstPhoneThatCanReceiveAnSms(): void
    {
        $contact = ClientNotificationContact::compose(
            clientId: self::CLIENT_ID,
            firstName: 'Marc',
            lastName: 'Durand',
            legalName: null,
            civility: 'mister',
            archived: true,
            contactMethods: [
                // A primary landline is no SMS address.
                self::method('phone', 'home', '+33142641800', true),
                self::method('email', 'home', 'marc@example.com'),
                self::method('phone', 'other', '+262692123456'),
                self::method('email', 'work', 'marc@travail.fr'),
                self::method('phone', 'mobile', '+33688214509'),
            ],
            preferredChannel: null,
            consents: [],
        );

        self::assertSame('marc@example.com', $contact->email);
        self::assertSame('+262692123456', $contact->mobile);
        self::assertTrue($contact->archived);
        self::assertSame('M. Durand', $contact->formalName());
    }

    public function testAStructureIsShownByItsLegalNameAndAnUnknownCivilityByTheFullName(): void
    {
        $contact = ClientNotificationContact::compose(
            clientId: self::CLIENT_ID,
            firstName: 'Julie',
            lastName: 'Martin',
            legalName: ' Élevage des Calanques ',
            civility: 'unspecified',
            archived: false,
            contactMethods: [self::method('phone', 'home', '+33142641800', true)],
            preferredChannel: null,
            consents: [],
        );

        self::assertSame('Élevage des Calanques', $contact->displayName);
        self::assertSame('', $contact->civility);
        self::assertSame('Julie Martin', $contact->formalName());
        self::assertNull($contact->email);
        self::assertNull($contact->mobile);
        self::assertNull($contact->transactionalChannel());

        $blankLegalName = ClientNotificationContact::compose(self::CLIENT_ID, 'Julie', 'Martin', '  ', 'unknown', false, [], null, []);
        self::assertSame('Julie Martin', $blankLegalName->displayName);
        self::assertSame('', $blankLegalName->civility);
    }

    public function testChannelsForAKindAreTheConsentedOnesWithAnAddress(): void
    {
        $contact = $this->contact(
            email: 'h.lambert@example.com',
            mobile: null,
            consents: [
                ClientNotificationContact::APPOINTMENT_REMINDERS => ['sms', 'email'],
                ClientNotificationContact::BILLING               => ['email', 'sms'],
            ],
        );

        self::assertSame(['email'], $contact->channelsFor(ClientNotificationContact::APPOINTMENT_REMINDERS));
        self::assertSame(['email'], $contact->channelsFor(ClientNotificationContact::BILLING));
        self::assertSame([], $contact->channelsFor(ClientNotificationContact::CLINICAL_DOCUMENTS));

        $both = $this->contact('h.lambert@example.com', '+33611223344', [ClientNotificationContact::BILLING => ['sms', 'email', 'phone']]);
        self::assertSame(['sms', 'email'], $both->channelsFor(ClientNotificationContact::BILLING));
    }

    public function testAnUnknownConsentKindIsRefused(): void
    {
        $this->expectException(\ValueError::class);

        $this->contact(null, null, [])->channelsFor('newsletter');
    }

    public function testTheTransactionalChannelFollowsThePreferredOneWhenItsAddressExists(): void
    {
        self::assertSame('sms', $this->contact('a@example.com', '+33611223344', [], 'sms')->transactionalChannel());
        self::assertSame('email', $this->contact('a@example.com', '+33611223344', [], 'email')->transactionalChannel());
        // Preferred SMS without a mobile, or a channel that cannot be automated: e-mail.
        self::assertSame('email', $this->contact('a@example.com', null, [], 'sms')->transactionalChannel());
        self::assertSame('email', $this->contact('a@example.com', '+33611223344', [], 'phone')->transactionalChannel());
        self::assertSame('email', $this->contact('a@example.com', '+33611223344')->transactionalChannel());
        // No e-mail: SMS, whatever the preference.
        self::assertSame('sms', $this->contact(null, '+33611223344', [], 'email')->transactionalChannel());
        self::assertNull($this->contact(null, null, [], 'sms')->transactionalChannel());
    }

    /**
     * @param array<string, list<string>> $consents
     */
    private function contact(?string $email, ?string $mobile, array $consents = [], ?string $preferred = null): ClientNotificationContact
    {
        return new ClientNotificationContact(self::CLIENT_ID, 'Hélène Lambert', 'Mme', 'Hélène', 'Lambert', $email, $mobile, $preferred, $consents, false);
    }

    /**
     * @return array{type: string, label: string, value: string, isPrimary: bool}
     */
    private static function method(string $type, string $label, string $value, bool $primary = false): array
    {
        return ['type' => $type, 'label' => $label, 'value' => $value, 'isPrimary' => $primary];
    }
}
