<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Client\Application\Query\GetClientNotificationContact\ClientNotificationContact;
use App\Context\Client\Application\Query\GetClientNotificationContact\GetClientNotificationContact;
use App\Context\Client\Application\Query\GetClientsNotificationContacts\GetClientsNotificationContacts;
use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\Civility;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ConsentKind;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * D8: the contact query reads the client, its contact methods and its
 * consents through the query bus, one SQL query for any number of clients.
 */
final class DbalClientNotificationContactReaderTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID       = '12345678-9abc-def0-1234-56789abcdef0';
    private const string OTHER_CLINIC_ID = '88888888-8888-7888-8888-888888888888';
    private const string LAMBERT         = '0192a000-0000-7000-8000-0000000000a1';
    private const string DURAND          = '0192a000-0000-7000-8000-0000000000a2';
    private const string ELSEWHERE       = '0192a000-0000-7000-8000-0000000000a3';

    public function testReadsTheContactOfOneClient(): void
    {
        $this->seed();

        $contact = $this->queryBus()->ask(new GetClientNotificationContact(self::CLINIC_ID, self::LAMBERT));

        self::assertInstanceOf(ClientNotificationContact::class, $contact);
        self::assertSame(self::LAMBERT, $contact->clientId);
        self::assertSame('Hélène Lambert', $contact->displayName);
        self::assertSame('Mme', $contact->civility);
        self::assertSame('h.lambert@example.com', $contact->email);
        self::assertSame('+33611223344', $contact->mobile);
        self::assertSame('sms', $contact->preferredChannel);
        self::assertSame(['appointment_reminders' => ['sms', 'email'], 'billing' => ['email']], $contact->consents);
        self::assertFalse($contact->archived);
        self::assertSame(['sms', 'email'], $contact->channelsFor(ClientNotificationContact::APPOINTMENT_REMINDERS));
        self::assertSame('sms', $contact->transactionalChannel());

        self::assertNull($this->queryBus()->ask(new GetClientNotificationContact(self::CLINIC_ID, self::ELSEWHERE)), 'a client of another clinic');
    }

    public function testReadsSeveralClientsInOneQuery(): void
    {
        $this->seed();

        $queries = $this->countQueries(function (): void {
            $contacts = $this->queryBus()->ask(new GetClientsNotificationContacts(self::CLINIC_ID, [self::LAMBERT, self::DURAND, self::ELSEWHERE]));

            self::assertIsArray($contacts);
            self::assertSame([self::LAMBERT, self::DURAND], array_keys($contacts));

            $durand = $contacts[self::DURAND];
            self::assertInstanceOf(ClientNotificationContact::class, $durand);
            self::assertSame('M. Durand', $durand->formalName());
            self::assertNull($durand->email);
            self::assertNull($durand->mobile, 'a landline only');
            self::assertNull($durand->preferredChannel);
            self::assertSame([], $durand->consents);
            self::assertTrue($durand->archived);
        });

        self::assertSame(1, $queries);
    }

    private function seed(): void
    {
        $now     = new \DateTimeImmutable('2026-10-01 09:00:00');
        $clients = self::getContainer()->get(ClientRepositoryInterface::class);
        \assert($clients instanceof ClientRepositoryInterface);

        $lambert = Client::create(
            ClientId::fromString(self::LAMBERT),
            ClinicId::fromString(self::CLINIC_ID),
            new ClientIdentity('Hélène', 'Lambert', Civility::MADAM),
            [
                ContactMethod::phone(PhoneNumber::fromString('+33142641800'), ContactLabel::HOME, true),
                ContactMethod::email(EmailAddress::fromString('h.lambert@travail.fr'), ContactLabel::WORK),
                ContactMethod::phone(PhoneNumber::fromString('+33611223344'), ContactLabel::MOBILE),
                ContactMethod::email(EmailAddress::fromString('h.lambert@example.com'), ContactLabel::HOME, true),
            ],
            $now,
        );
        $lambert->updateCommunicationPreferences(ContactChannel::SMS, [
            ConsentKind::APPOINTMENT_REMINDERS->value => [ContactChannel::SMS, ContactChannel::EMAIL],
            ConsentKind::BILLING->value               => [ContactChannel::EMAIL],
        ], $now);
        $clients->save($lambert);

        $durand = Client::create(
            ClientId::fromString(self::DURAND),
            ClinicId::fromString(self::CLINIC_ID),
            new ClientIdentity('Marc', 'Durand', Civility::MISTER),
            [ContactMethod::phone(PhoneNumber::fromString('+33142641801'), ContactLabel::HOME, true)],
            $now,
        );
        $durand->archive($now);
        $clients->save($durand);

        $clients->save(Client::create(
            ClientId::fromString(self::ELSEWHERE),
            ClinicId::fromString(self::OTHER_CLINIC_ID),
            new ClientIdentity('Paul', 'Ailleurs'),
            [ContactMethod::email(EmailAddress::fromString('paul@example.com'), ContactLabel::HOME, true)],
            $now,
        ));
    }

    /** SELECT statements the server ran for $run. */
    private function countQueries(\Closure $run): int
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);
        $selects = static function () use ($connection): int {
            $status = $connection->fetchAssociative("SHOW SESSION STATUS LIKE 'Com_select'");
            \assert(\is_array($status) && is_numeric($status['Value']));

            return (int) $status['Value'];
        };

        $before = $selects();
        $run();

        return $selects() - $before;
    }

    private function queryBus(): QueryBusInterface
    {
        $bus = self::getContainer()->get(QueryBusInterface::class);
        \assert($bus instanceof QueryBusInterface);

        return $bus;
    }
}
