<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Query\GetClientById;

use App\Context\Client\Application\Query\GetClientById\AuthorizedPersonDto;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\GetClientById\ConsentDto;
use PHPUnit\Framework\TestCase;

final class ClientViewConsentsTest extends TestCase
{
    public function testExposesAuthorizedPersonPreferredChannelAndConsents(): void
    {
        $person  = new AuthorizedPersonDto('mister', 'Marc', 'Lambert', 'spouse', '+33688214509', '2022-03-14T10:00:00+00:00');
        $consent = new ConsentDto('appointment_reminders', ['sms', 'email'], '2019-03-12T10:00:00+00:00');

        $view = new ClientView(
            id: '01234567-89ab-cdef-0123-456789abcdef',
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            firstName: 'Hélène',
            lastName: 'Lambert',
            status: 'active',
            contactMethods: [],
            postalAddress: null,
            createdAt: '2019-03-12T10:00:00+00:00',
            updatedAt: '2019-03-12T10:00:00+00:00',
            authorizedPerson: $person,
            preferredChannel: 'sms',
            consents: [$consent],
        );

        self::assertSame($person, $view->authorizedPerson);
        self::assertSame('Marc Lambert', $person->fullName());
        self::assertSame('mister', $person->civility);
        self::assertSame('spouse', $person->relationship);
        self::assertSame('+33688214509', $person->phoneNumber);
        self::assertSame('2022-03-14T10:00:00+00:00', $person->authorizedAt);
        self::assertSame('sms', $view->preferredChannel);
        self::assertSame($consent, $view->consentFor('appointment_reminders'));
        self::assertSame(['sms', 'email'], $consent->channels);
        self::assertSame('2019-03-12T10:00:00+00:00', $consent->grantedAt);
        self::assertNull($view->consentFor('marketing'));
    }

    public function testDefaultsToNoAuthorizedPersonAndNoConsent(): void
    {
        $view = new ClientView(
            id: '01234567-89ab-cdef-0123-456789abcdef',
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            firstName: 'Hélène',
            lastName: 'Lambert',
            status: 'active',
            contactMethods: [],
            postalAddress: null,
            createdAt: '2019-03-12T10:00:00+00:00',
            updatedAt: '2019-03-12T10:00:00+00:00',
        );

        self::assertNull($view->authorizedPerson);
        self::assertNull($view->preferredChannel);
        self::assertSame([], $view->consents);
        self::assertNull($view->consentFor('appointment_reminders'));
    }
}
