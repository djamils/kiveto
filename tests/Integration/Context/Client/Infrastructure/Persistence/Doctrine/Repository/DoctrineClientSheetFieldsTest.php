<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\AuthorizedPerson;
use App\Context\Client\Domain\ValueObject\AuthorizedPersonRelationship;
use App\Context\Client\Domain\ValueObject\Civility;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ConsentKind;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Shared\Domain\ValueObject\PostalAddress;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * The fields the client sheet edits after creation — postal address,
 * authorized person, communication preferences — must survive an update of
 * an existing row, not only the first insert.
 */
final class DoctrineClientSheetFieldsTest extends KernelTestCase
{
    use Factories;

    private const string CLIENT_ID = '01234567-89ab-cdef-0123-456789abcdef';
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';

    public function testUpdatingAnExistingClientPersistsAddressAuthorizedPersonAndPreferences(): void
    {
        $repository = $this->repository();
        $createdAt  = new \DateTimeImmutable('2019-03-12 10:00:00');

        $repository->save(Client::create(
            ClientId::fromString(self::CLIENT_ID),
            ClinicId::fromString(self::CLINIC_ID),
            new ClientIdentity('Hélène', 'Lambert'),
            [ContactMethod::email(EmailAddress::fromString('h.lambert@example.com'), ContactLabel::HOME, true)],
            $createdAt,
        ));
        $this->entityManager()->clear();

        $now    = new \DateTimeImmutable('2026-09-30T09:00:00+00:00');
        $client = $repository->get(ClinicId::fromString(self::CLINIC_ID), ClientId::fromString(self::CLIENT_ID));

        $client->updatePostalAddress(
            PostalAddress::create(streetLine1: '24 rue Lepic', city: 'Paris', countryCode: 'FR', postalCode: '75018'),
            $now,
        );
        $client->changeAuthorizedPerson(new AuthorizedPerson(
            Civility::MISTER,
            'Marc',
            'Lambert',
            AuthorizedPersonRelationship::SPOUSE,
            PhoneNumber::fromString('+33688214509'),
            $now,
        ), $now);
        $client->updateCommunicationPreferences(
            ContactChannel::SMS,
            [ConsentKind::APPOINTMENT_REMINDERS->value => [ContactChannel::SMS, ContactChannel::EMAIL]],
            $now,
        );

        $repository->save($client);
        $this->entityManager()->clear();

        $reloaded = $repository->get(ClinicId::fromString(self::CLINIC_ID), ClientId::fromString(self::CLIENT_ID));

        $address = $reloaded->postalAddress();
        self::assertNotNull($address);
        self::assertSame('24 rue Lepic', $address->streetLine1);
        self::assertSame('75018', $address->postalCode);
        self::assertSame('Paris', $address->city);

        $person = $reloaded->authorizedPerson();
        self::assertNotNull($person);
        self::assertSame(Civility::MISTER, $person->civility);
        self::assertSame('Marc Lambert', $person->fullName());
        self::assertSame(AuthorizedPersonRelationship::SPOUSE, $person->relationship);
        self::assertSame('+33688214509', $person->phoneNumber?->toString());
        self::assertSame($now->format(\DATE_ATOM), $person->authorizedAt->format(\DATE_ATOM));

        $preferences = $reloaded->communicationPreferences();
        self::assertSame(ContactChannel::SMS, $preferences->preferredChannel);

        $consent = $preferences->consentFor(ConsentKind::APPOINTMENT_REMINDERS);
        self::assertNotNull($consent);
        self::assertSame([ContactChannel::SMS, ContactChannel::EMAIL], $consent->channels);
        self::assertSame($now->format(\DATE_ATOM), $consent->grantedAt->format(\DATE_ATOM));
    }

    public function testWithdrawingEverythingClearsTheStoredValues(): void
    {
        $repository = $this->repository();
        $now        = new \DateTimeImmutable('2026-09-30T09:00:00+00:00');

        $client = Client::create(
            ClientId::fromString(self::CLIENT_ID),
            ClinicId::fromString(self::CLINIC_ID),
            new ClientIdentity('Hélène', 'Lambert'),
            [ContactMethod::email(EmailAddress::fromString('h.lambert@example.com'), ContactLabel::HOME, true)],
            $now,
        );
        $client->changeAuthorizedPerson(new AuthorizedPerson(
            Civility::MADAM,
            'Julie',
            'Lambert',
            AuthorizedPersonRelationship::CHILD,
            null,
            $now,
        ), $now);
        $client->updateCommunicationPreferences(ContactChannel::EMAIL, [], $now);
        $repository->save($client);
        $this->entityManager()->clear();

        $stored = $repository->get(ClinicId::fromString(self::CLINIC_ID), ClientId::fromString(self::CLIENT_ID));
        self::assertNotNull($stored->authorizedPerson());
        self::assertSame(ContactChannel::EMAIL, $stored->communicationPreferences()->preferredChannel);

        $stored->changeAuthorizedPerson(null, $now);
        $stored->updateCommunicationPreferences(null, [], $now);
        $repository->save($stored);
        $this->entityManager()->clear();

        $reloaded = $repository->get(ClinicId::fromString(self::CLINIC_ID), ClientId::fromString(self::CLIENT_ID));

        self::assertNull($reloaded->authorizedPerson());
        self::assertNull($reloaded->communicationPreferences()->preferredChannel);
        self::assertSame([], $reloaded->communicationPreferences()->consents);
    }

    public function testTheReadModelExposesAuthorizedPersonAndConsents(): void
    {
        $repository = $this->repository();
        $now        = new \DateTimeImmutable('2026-09-30T09:00:00+00:00');

        $client = Client::create(
            ClientId::fromString(self::CLIENT_ID),
            ClinicId::fromString(self::CLINIC_ID),
            new ClientIdentity('Hélène', 'Lambert'),
            [ContactMethod::email(EmailAddress::fromString('h.lambert@example.com'), ContactLabel::HOME, true)],
            $now,
        );
        $client->changeAuthorizedPerson(new AuthorizedPerson(
            Civility::MISTER,
            'Marc',
            'Lambert',
            AuthorizedPersonRelationship::SPOUSE,
            PhoneNumber::fromString('+33688214509'),
            $now,
        ), $now);
        $client->updateCommunicationPreferences(
            ContactChannel::SMS,
            [ConsentKind::MARKETING->value => [ContactChannel::EMAIL]],
            $now,
        );
        $repository->save($client);
        $this->entityManager()->clear();

        $readRepository = static::getContainer()->get(ClientReadRepositoryInterface::class);
        \assert($readRepository instanceof ClientReadRepositoryInterface);

        $view = $readRepository->findById(ClinicId::fromString(self::CLINIC_ID), ClientId::fromString(self::CLIENT_ID));

        self::assertNotNull($view);
        self::assertNotNull($view->authorizedPerson);
        self::assertSame('mister', $view->authorizedPerson->civility);
        self::assertSame('Marc', $view->authorizedPerson->firstName);
        self::assertSame('Lambert', $view->authorizedPerson->lastName);
        self::assertSame('spouse', $view->authorizedPerson->relationship);
        self::assertSame('+33688214509', $view->authorizedPerson->phoneNumber);
        self::assertSame($now->format(\DATE_ATOM), $view->authorizedPerson->authorizedAt);
        self::assertSame('sms', $view->preferredChannel);
        self::assertCount(1, $view->consents);
        self::assertSame('marketing', $view->consents[0]->kind);
        self::assertSame(['email'], $view->consents[0]->channels);
        self::assertSame($now->format(\DATE_ATOM), $view->consents[0]->grantedAt);
    }

    public function testTheReadModelIsEmptyForAClientWithNothingRecorded(): void
    {
        $this->repository()->save(Client::create(
            ClientId::fromString(self::CLIENT_ID),
            ClinicId::fromString(self::CLINIC_ID),
            new ClientIdentity('Hélène', 'Lambert'),
            [ContactMethod::email(EmailAddress::fromString('h.lambert@example.com'), ContactLabel::HOME, true)],
            new \DateTimeImmutable('2019-03-12 10:00:00'),
        ));
        $this->entityManager()->clear();

        $readRepository = static::getContainer()->get(ClientReadRepositoryInterface::class);
        \assert($readRepository instanceof ClientReadRepositoryInterface);

        $view = $readRepository->findById(ClinicId::fromString(self::CLINIC_ID), ClientId::fromString(self::CLIENT_ID));

        self::assertNotNull($view);
        self::assertNull($view->authorizedPerson);
        self::assertNull($view->preferredChannel);
        self::assertSame([], $view->consents);
    }

    private function repository(): ClientRepositoryInterface
    {
        $repository = static::getContainer()->get(ClientRepositoryInterface::class);
        \assert($repository instanceof ClientRepositoryInterface);

        return $repository;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        return $entityManager;
    }
}
