<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Domain;

use App\Client\Domain\Client;
use App\Client\Domain\Event\ClientArchived;
use App\Client\Domain\Event\ClientContactMethodsReplaced;
use App\Client\Domain\Event\ClientCreated;
use App\Client\Domain\Event\ClientIdentityUpdated;
use App\Client\Domain\Event\ClientPostalAddressUpdated;
use App\Client\Domain\Event\ClientUnarchived;
use App\Client\Domain\Exception\ClientAlreadyArchivedException;
use App\Client\Domain\Exception\ClientArchivedCannotBeModifiedException;
use App\Client\Domain\Exception\ClientMustHaveAtLeastOneContactMethodException;
use App\Client\Domain\Exception\DuplicateContactMethodException;
use App\Client\Domain\Exception\PrimaryContactMethodConflictException;
use App\Client\Domain\ValueObject\ClientId;
use App\Client\Domain\ValueObject\ClientIdentity;
use App\Client\Domain\ValueObject\ClientStatus;
use App\Client\Domain\ValueObject\ContactLabel;
use App\Client\Domain\ValueObject\ContactMethod;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Shared\Domain\ValueObject\PostalAddress;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public function testCreateClientWithValidData(): void
    {
        $clientId       = ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId       = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $identity       = new ClientIdentity('John', 'Doe');
        $contactMethods = [
            ContactMethod::email(EmailAddress::fromString('john@example.com'), ContactLabel::WORK, true),
        ];
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');

        $client = Client::create($clientId, $clinicId, $identity, $contactMethods, $createdAt);

        self::assertSame($clientId, $client->id());
        self::assertSame($clinicId, $client->clinicId());
        self::assertSame($identity, $client->identity());
        self::assertSame(ClientStatus::ACTIVE, $client->status());
        self::assertSame($contactMethods, $client->contactMethods());
        self::assertNull($client->postalAddress());
        self::assertSame($createdAt, $client->createdAt());
        self::assertSame($createdAt, $client->updatedAt());
        self::assertFalse($client->isArchived());
    }

    public function testCreateClientRecordsClientCreatedEvent(): void
    {
        $clientId       = ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId       = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $identity       = new ClientIdentity('John', 'Doe');
        $contactMethods = [
            ContactMethod::email(EmailAddress::fromString('john@example.com'), ContactLabel::WORK, true),
        ];
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');

        $client = Client::create($clientId, $clinicId, $identity, $contactMethods, $createdAt);
        $events = $client->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(ClientCreated::class, $events[0]);
        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $events[0]->aggregateId());
    }

    public function testCreateClientThrowsExceptionWhenContactMethodsEmpty(): void
    {
        $clientId  = ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId  = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $identity  = new ClientIdentity('John', 'Doe');
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');

        $this->expectException(ClientMustHaveAtLeastOneContactMethodException::class);

        Client::create($clientId, $clinicId, $identity, [], $createdAt);
    }

    public function testCreateClientThrowsExceptionWhenDuplicateContactMethods(): void
    {
        $clientId       = ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId       = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $identity       = new ClientIdentity('John', 'Doe');
        $contactMethods = [
            ContactMethod::email(EmailAddress::fromString('john@example.com'), ContactLabel::WORK, true),
            ContactMethod::email(EmailAddress::fromString('john@example.com'), ContactLabel::HOME, false),
        ];
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');

        $this->expectException(DuplicateContactMethodException::class);

        Client::create($clientId, $clinicId, $identity, $contactMethods, $createdAt);
    }

    public function testCreateClientThrowsExceptionWhenMultiplePrimaryPhones(): void
    {
        $clientId       = ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId       = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $identity       = new ClientIdentity('John', 'Doe');
        $contactMethods = [
            ContactMethod::phone(PhoneNumber::fromString('+33612345678'), ContactLabel::MOBILE, true),
            ContactMethod::phone(PhoneNumber::fromString('+33698765432'), ContactLabel::HOME, true),
        ];
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');

        $this->expectException(PrimaryContactMethodConflictException::class);
        $this->expectExceptionMessage('Only one primary phone contact method is allowed.');

        Client::create($clientId, $clinicId, $identity, $contactMethods, $createdAt);
    }

    public function testCreateClientThrowsExceptionWhenMultiplePrimaryEmails(): void
    {
        $clientId       = ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId       = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $identity       = new ClientIdentity('John', 'Doe');
        $contactMethods = [
            ContactMethod::email(EmailAddress::fromString('john@example.com'), ContactLabel::WORK, true),
            ContactMethod::email(EmailAddress::fromString('jane@example.com'), ContactLabel::HOME, true),
        ];
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');

        $this->expectException(PrimaryContactMethodConflictException::class);
        $this->expectExceptionMessage('Only one primary email contact method is allowed.');

        Client::create($clientId, $clinicId, $identity, $contactMethods, $createdAt);
    }

    public function testReconstituteClient(): void
    {
        $clientId       = ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId       = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $identity       = new ClientIdentity('John', 'Doe');
        $contactMethods = [
            ContactMethod::email(EmailAddress::fromString('john@example.com'), ContactLabel::WORK, true),
        ];
        $postalAddress = new PostalAddress('123 Main St', 'Paris', 'FR', null, '75001', null);
        $createdAt     = new \DateTimeImmutable('2024-01-01 10:00:00');
        $updatedAt     = new \DateTimeImmutable('2024-01-02 14:00:00');

        $client = Client::reconstitute(
            $clientId,
            $clinicId,
            $identity,
            ClientStatus::ARCHIVED,
            $contactMethods,
            $createdAt,
            $updatedAt,
            $postalAddress
        );

        self::assertSame($clientId, $client->id());
        self::assertSame($clinicId, $client->clinicId());
        self::assertSame($identity, $client->identity());
        self::assertSame(ClientStatus::ARCHIVED, $client->status());
        self::assertSame($contactMethods, $client->contactMethods());
        self::assertSame($postalAddress, $client->postalAddress());
        self::assertSame($createdAt, $client->createdAt());
        self::assertSame($updatedAt, $client->updatedAt());
        self::assertTrue($client->isArchived());
    }

    public function testReconstituteClientDoesNotRecordEvents(): void
    {
        $clientId       = ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId       = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $identity       = new ClientIdentity('John', 'Doe');
        $contactMethods = [
            ContactMethod::email(EmailAddress::fromString('john@example.com'), ContactLabel::WORK, true),
        ];
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');
        $updatedAt = new \DateTimeImmutable('2024-01-02 14:00:00');

        $client = Client::reconstitute(
            $clientId,
            $clinicId,
            $identity,
            ClientStatus::ACTIVE,
            $contactMethods,
            $createdAt,
            $updatedAt
        );

        self::assertEmpty($client->recordedDomainEvents());
    }

    public function testUpdateIdentity(): void
    {
        $client      = $this->createActiveClient();
        $newIdentity = new ClientIdentity('Jane', 'Smith');
        $now         = new \DateTimeImmutable('2024-01-02 14:00:00');

        $client->updateIdentity($newIdentity, $now);

        self::assertSame($newIdentity, $client->identity());
        self::assertSame($now, $client->updatedAt());
    }

    public function testUpdateIdentityRecordsEvent(): void
    {
        $client      = $this->createActiveClient();
        $newIdentity = new ClientIdentity('Jane', 'Smith');
        $now         = new \DateTimeImmutable('2024-01-02 14:00:00');

        $_ = $client->pullDomainEvents(); // Clear creation event
        $client->updateIdentity($newIdentity, $now);
        $events = $client->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(ClientIdentityUpdated::class, $events[0]);
    }

    public function testUpdateIdentityThrowsExceptionWhenArchived(): void
    {
        $client      = $this->createArchivedClient();
        $newIdentity = new ClientIdentity('Jane', 'Smith');
        $now         = new \DateTimeImmutable('2024-01-02 14:00:00');

        $this->expectException(ClientArchivedCannotBeModifiedException::class);

        $client->updateIdentity($newIdentity, $now);
    }

    public function testReplaceContactMethods(): void
    {
        $client            = $this->createActiveClient();
        $newContactMethods = [
            ContactMethod::phone(PhoneNumber::fromString('+33612345678'), ContactLabel::MOBILE, true),
            ContactMethod::email(EmailAddress::fromString('new@example.com'), ContactLabel::WORK, true),
        ];
        $now = new \DateTimeImmutable('2024-01-02 14:00:00');

        $client->replaceContactMethods($newContactMethods, $now);

        self::assertSame($newContactMethods, $client->contactMethods());
        self::assertSame($now, $client->updatedAt());
    }

    public function testReplaceContactMethodsRecordsEvent(): void
    {
        $client            = $this->createActiveClient();
        $newContactMethods = [
            ContactMethod::phone(PhoneNumber::fromString('+33612345678'), ContactLabel::MOBILE, true),
        ];
        $now = new \DateTimeImmutable('2024-01-02 14:00:00');

        $_ = $client->pullDomainEvents(); // Clear creation event
        $client->replaceContactMethods($newContactMethods, $now);
        $events = $client->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(ClientContactMethodsReplaced::class, $events[0]);
    }

    public function testReplaceContactMethodsThrowsExceptionWhenArchived(): void
    {
        $client            = $this->createArchivedClient();
        $newContactMethods = [
            ContactMethod::phone(PhoneNumber::fromString('+33612345678'), ContactLabel::MOBILE, true),
        ];
        $now = new \DateTimeImmutable('2024-01-02 14:00:00');

        $this->expectException(ClientArchivedCannotBeModifiedException::class);

        $client->replaceContactMethods($newContactMethods, $now);
    }

    public function testReplaceContactMethodsThrowsExceptionWhenEmpty(): void
    {
        $client = $this->createActiveClient();
        $now    = new \DateTimeImmutable('2024-01-02 14:00:00');

        $this->expectException(ClientMustHaveAtLeastOneContactMethodException::class);

        $client->replaceContactMethods([], $now);
    }

    public function testArchiveClient(): void
    {
        $client = $this->createActiveClient();
        $now    = new \DateTimeImmutable('2024-01-02 14:00:00');

        $client->archive($now);

        self::assertSame(ClientStatus::ARCHIVED, $client->status());
        self::assertTrue($client->isArchived());
        self::assertSame($now, $client->updatedAt());
    }

    public function testArchiveClientRecordsEvent(): void
    {
        $client = $this->createActiveClient();
        $now    = new \DateTimeImmutable('2024-01-02 14:00:00');

        $_ = $client->pullDomainEvents(); // Clear creation event
        $client->archive($now);
        $events = $client->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(ClientArchived::class, $events[0]);
    }

    public function testArchiveClientThrowsExceptionWhenAlreadyArchived(): void
    {
        $client = $this->createArchivedClient();
        $now    = new \DateTimeImmutable('2024-01-02 14:00:00');

        $this->expectException(ClientAlreadyArchivedException::class);

        $client->archive($now);
    }

    public function testUnarchiveClient(): void
    {
        $client = $this->createArchivedClient();
        $now    = new \DateTimeImmutable('2024-01-02 14:00:00');

        $client->unarchive($now);

        self::assertSame(ClientStatus::ACTIVE, $client->status());
        self::assertFalse($client->isArchived());
        self::assertSame($now, $client->updatedAt());
    }

    public function testUnarchiveClientRecordsEvent(): void
    {
        $client = $this->createArchivedClient();
        $now    = new \DateTimeImmutable('2024-01-02 14:00:00');

        $_ = $client->pullDomainEvents(); // Clear any events
        $client->unarchive($now);
        $events = $client->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(ClientUnarchived::class, $events[0]);
    }

    public function testUnarchiveClientThrowsExceptionWhenAlreadyActive(): void
    {
        $client = $this->createActiveClient();
        $now    = new \DateTimeImmutable('2024-01-02 14:00:00');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('is already active');

        $client->unarchive($now);
    }

    public function testUpdatePostalAddress(): void
    {
        $client        = $this->createActiveClient();
        $postalAddress = new PostalAddress('123 Main St', 'Paris', 'FR', null, '75001', null);
        $now           = new \DateTimeImmutable('2024-01-02 14:00:00');

        $client->updatePostalAddress($postalAddress, $now);

        self::assertSame($postalAddress, $client->postalAddress());
        self::assertSame($now, $client->updatedAt());
    }

    public function testUpdatePostalAddressRecordsEvent(): void
    {
        $client        = $this->createActiveClient();
        $postalAddress = new PostalAddress('123 Main St', 'Paris', 'FR', null, '75001', null);
        $now           = new \DateTimeImmutable('2024-01-02 14:00:00');

        $_ = $client->pullDomainEvents(); // Clear creation event
        $client->updatePostalAddress($postalAddress, $now);
        $events = $client->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(ClientPostalAddressUpdated::class, $events[0]);
    }

    public function testUpdatePostalAddressWithNull(): void
    {
        $client        = $this->createActiveClient();
        $postalAddress = new PostalAddress('123 Main St', 'Paris', 'FR', null, '75001', null);
        $now           = new \DateTimeImmutable('2024-01-02 14:00:00');

        $client->updatePostalAddress($postalAddress, $now);
        self::assertSame($postalAddress, $client->postalAddress());

        $client->updatePostalAddress(null, $now);
        self::assertNull($client->postalAddress());
    }

    public function testUpdatePostalAddressThrowsExceptionWhenArchived(): void
    {
        $client        = $this->createArchivedClient();
        $postalAddress = new PostalAddress('123 Main St', 'Paris', 'FR', null, '75001', null);
        $now           = new \DateTimeImmutable('2024-01-02 14:00:00');

        $this->expectException(ClientArchivedCannotBeModifiedException::class);

        $client->updatePostalAddress($postalAddress, $now);
    }

    private function createActiveClient(): Client
    {
        $clientId       = ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId       = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $identity       = new ClientIdentity('John', 'Doe');
        $contactMethods = [
            ContactMethod::email(EmailAddress::fromString('john@example.com'), ContactLabel::WORK, true),
        ];
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');

        return Client::create($clientId, $clinicId, $identity, $contactMethods, $createdAt);
    }

    private function createArchivedClient(): Client
    {
        $clientId       = ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId       = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $identity       = new ClientIdentity('John', 'Doe');
        $contactMethods = [
            ContactMethod::email(EmailAddress::fromString('john@example.com'), ContactLabel::WORK, true),
        ];
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');
        $updatedAt = new \DateTimeImmutable('2024-01-01 10:00:00');

        return Client::reconstitute(
            $clientId,
            $clinicId,
            $identity,
            ClientStatus::ARCHIVED,
            $contactMethods,
            $createdAt,
            $updatedAt
        );
    }
}
