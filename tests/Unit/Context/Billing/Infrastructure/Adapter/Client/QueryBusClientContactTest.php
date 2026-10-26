<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Infrastructure\Adapter\Client;

use App\Context\Billing\Infrastructure\Adapter\Client\QueryBusClientContact;
use App\Context\Client\Application\Query\GetClientNotificationContact\ClientNotificationContact;
use App\Context\Client\Application\Query\GetClientNotificationContact\GetClientNotificationContact;
use App\Context\Client\Application\Query\GetClientsNotificationContacts\GetClientsNotificationContacts;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\TestCase;

final class QueryBusClientContactTest extends TestCase
{
    public function testTheBillingConsentChoosesTheReminderChannels(): void
    {
        $bus = new AnsweringQueryBus([GetClientNotificationContact::class => $this->contact('c-1', 'Mme', ['billing' => ['sms', 'email']])]);

        $contact = new QueryBusClientContact($bus)->contactOf('clinic-1', 'c-1');

        self::assertNotNull($contact);
        self::assertSame(['c-1', 'Sophie Dubois', 'Madame Dubois', 's.dubois@email.fr', '+33612345678', ['SMS', 'EMAIL'], 'EMAIL', false], [
            $contact->clientId, $contact->name, $contact->addressee, $contact->email, $contact->mobile, $contact->billingChannels, $contact->transactionalChannel, $contact->archived,
        ]);
        self::assertSame(['SMS', 'EMAIL'], $contact->reminderChannels());
        self::assertTrue($contact->canReceiveInvoice());
        self::assertInstanceOf(GetClientNotificationContact::class, $bus->asked[0]);
    }

    public function testWithoutBillingConsentTheTransactionalChannelIsUsed(): void
    {
        $bus = new AnsweringQueryBus([GetClientNotificationContact::class => $this->contact('c-1', '', [], email: null)]);

        $contact = new QueryBusClientContact($bus)->contactOf('clinic-1', 'c-1');

        self::assertNotNull($contact);
        self::assertSame('Sophie Dubois', $contact->addressee);
        self::assertSame(['SMS'], $contact->reminderChannels());
        self::assertFalse($contact->canReceiveInvoice());
    }

    public function testAnUnreachableOrUnknownClient(): void
    {
        $unreachable = new QueryBusClientContact(new AnsweringQueryBus([GetClientNotificationContact::class => $this->contact('c-1', 'M.', [], email: null, mobile: null)]))->contactOf('clinic-1', 'c-1');

        self::assertNotNull($unreachable);
        self::assertSame('Monsieur Dubois', $unreachable->addressee);
        self::assertNull($unreachable->transactionalChannel);
        self::assertSame([], $unreachable->reminderChannels());
        self::assertNull(new QueryBusClientContact(new AnsweringQueryBus())->contactOf('clinic-1', 'c-1'));
    }

    public function testAPageOfPayersIsReadInOneQuery(): void
    {
        $bus = new AnsweringQueryBus([GetClientsNotificationContacts::class => [
            'c-1' => $this->contact('c-1', 'Mme', [], archived: true),
            'c-2' => $this->contact('c-2', 'Mme', []),
            'x'   => 'not a contact',
        ]]);

        $contacts = new QueryBusClientContact($bus)->contactsOf('clinic-1', ['c-1', 'c-2']);

        self::assertSame(['c-1', 'c-2'], array_keys($contacts));
        self::assertSame([], $contacts['c-1']->reminderChannels(), 'An archived client is not reminded.');
        self::assertFalse($contacts['c-1']->canReceiveInvoice());
        self::assertCount(1, $bus->asked);
        self::assertSame([], new QueryBusClientContact(new AnsweringQueryBus())->contactsOf('clinic-1', ['c-1']));
    }

    /**
     * @param array<string, list<string>> $consents
     */
    private function contact(string $id, string $civility, array $consents, ?string $email = 's.dubois@email.fr', ?string $mobile = '+33612345678', bool $archived = false): ClientNotificationContact
    {
        return new ClientNotificationContact($id, 'Sophie Dubois', $civility, 'Sophie', 'Dubois', $email, $mobile, null, $consents, $archived);
    }
}
