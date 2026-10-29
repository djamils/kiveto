<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Infrastructure\Adapter\Client;

use App\Context\Client\Application\Query\GetClientNotificationContact\ClientNotificationContact;
use App\Context\Client\Application\Query\GetClientNotificationContact\GetClientNotificationContact;
use App\Context\Scheduling\Application\Port\ClientContact;
use App\Context\Scheduling\Infrastructure\Adapter\Client\QueryBusClientContact;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\TestCase;

final class QueryBusClientContactTest extends TestCase
{
    public function testTheConsentRulesOfAppointmentsAreApplied(): void
    {
        $contact = $this->contact(new ClientNotificationContact(
            clientId: 'client-1',
            displayName: 'Sophie Dubois',
            civility: 'Mme',
            firstName: 'Sophie',
            lastName: 'Dubois',
            email: 's.dubois@email.fr',
            mobile: '+33612345678',
            preferredChannel: 'sms',
            consents: ['appointment_reminders' => ['sms', 'email'], 'billing' => ['email']],
            archived: false,
        ));

        self::assertNotNull($contact);
        self::assertSame(['client-1', 'Sophie Dubois', 'Madame Dubois', 's.dubois@email.fr', '+33612345678', ClientContact::SMS, [ClientContact::SMS, ClientContact::EMAIL], false], [
            $contact->clientId, $contact->name, $contact->addressee, $contact->email, $contact->mobile, $contact->transactionalChannel, $contact->reminderChannels, $contact->archived,
        ]);
        self::assertTrue($contact->acceptsReminderOn(ClientContact::EMAIL));
    }

    public function testAClientWithoutCivilityOrAddressIsGreetedByName(): void
    {
        $contact = $this->contact(new ClientNotificationContact('client-1', 'Paul Martin', '', 'Paul', 'Martin', null, null, null, [], true));
        self::assertNotNull($contact);
        self::assertSame(['Paul Martin', null, [], true], [$contact->addressee, $contact->transactionalChannel, $contact->reminderChannels, $contact->archived]);

        $mister = $this->contact(new ClientNotificationContact('client-2', 'Paul Martin', 'M.', 'Paul', 'Martin', 'paul@example.fr', null, null, [], false));
        self::assertSame(['Monsieur Martin', ClientContact::EMAIL], [$mister?->addressee, $mister?->transactionalChannel]);
    }

    public function testAClientOfAnotherClinicCannotBeReached(): void
    {
        self::assertNull($this->contact(null));
    }

    private function contact(?ClientNotificationContact $answer): ?ClientContact
    {
        $bus = new AnsweringQueryBus([GetClientNotificationContact::class => $answer]);

        return new QueryBusClientContact($bus)->contactOf('clinic-1', 'client-1');
    }
}
