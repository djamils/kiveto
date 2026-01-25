<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Infrastructure\Persistence\Doctrine\Mapper;

use App\Client\Domain\Client;
use App\Client\Domain\ValueObject\ClientId;
use App\Client\Domain\ValueObject\ClientIdentity;
use App\Client\Domain\ValueObject\ClientStatus;
use App\Client\Domain\ValueObject\ContactLabel;
use App\Client\Domain\ValueObject\ContactMethod;
use App\Client\Domain\ValueObject\ContactMethodType;
use App\Client\Infrastructure\Persistence\Doctrine\Embeddable\PostalAddressEmbeddable;
use App\Client\Infrastructure\Persistence\Doctrine\Entity\ClientEntity;
use App\Client\Infrastructure\Persistence\Doctrine\Entity\ContactMethodEntity;
use App\Client\Infrastructure\Persistence\Doctrine\Mapper\ClientMapper;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Shared\Domain\ValueObject\PostalAddress;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ClientMapperTest extends TestCase
{
    private ClientMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ClientMapper();
    }

    public function testToDomainMapsClientEntityToDomainWithPhoneContact(): void
    {
        $clientId  = Uuid::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId  = Uuid::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');
        $updatedAt = new \DateTimeImmutable('2024-01-02 11:00:00');

        $clientEntity = new ClientEntity();
        $clientEntity->setId($clientId);
        $clientEntity->setClinicId($clinicId);
        $clientEntity->setFirstName('John');
        $clientEntity->setLastName('Doe');
        $clientEntity->setStatus(ClientStatus::ACTIVE);
        $clientEntity->setCreatedAt($createdAt);
        $clientEntity->setUpdatedAt($updatedAt);
        $clientEntity->setPostalAddress(new PostalAddressEmbeddable());

        $contactMethodEntity = new ContactMethodEntity();
        $contactMethodEntity->setId(Uuid::v7());
        $contactMethodEntity->setClientId($clientId);
        $contactMethodEntity->setType(ContactMethodType::PHONE);
        $contactMethodEntity->setLabel(ContactLabel::MOBILE);
        $contactMethodEntity->setValue('+33612345678');
        $contactMethodEntity->setIsPrimary(true);

        $client = $this->mapper->toDomain($clientEntity, [$contactMethodEntity]);

        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $client->id()->toString());
        self::assertSame('12345678-9abc-def0-1234-56789abcdef0', $client->clinicId()->toString());
        self::assertSame('John', $client->identity()->firstName);
        self::assertSame('Doe', $client->identity()->lastName);
        self::assertSame(ClientStatus::ACTIVE, $client->status());
        self::assertCount(1, $client->contactMethods());
        self::assertTrue($client->contactMethods()[0]->isPhone());
        self::assertSame('+33612345678', $client->contactMethods()[0]->value);
        self::assertNull($client->postalAddress());
        self::assertSame($createdAt, $client->createdAt());
        self::assertSame($updatedAt, $client->updatedAt());
    }

    public function testToDomainMapsClientEntityToDomainWithEmailContact(): void
    {
        $clientId  = Uuid::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId  = Uuid::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');
        $updatedAt = new \DateTimeImmutable('2024-01-02 11:00:00');

        $clientEntity = new ClientEntity();
        $clientEntity->setId($clientId);
        $clientEntity->setClinicId($clinicId);
        $clientEntity->setFirstName('Jane');
        $clientEntity->setLastName('Smith');
        $clientEntity->setStatus(ClientStatus::ARCHIVED);
        $clientEntity->setCreatedAt($createdAt);
        $clientEntity->setUpdatedAt($updatedAt);
        $clientEntity->setPostalAddress(new PostalAddressEmbeddable());

        $contactMethodEntity = new ContactMethodEntity();
        $contactMethodEntity->setId(Uuid::v7());
        $contactMethodEntity->setClientId($clientId);
        $contactMethodEntity->setType(ContactMethodType::EMAIL);
        $contactMethodEntity->setLabel(ContactLabel::WORK);
        $contactMethodEntity->setValue('jane@example.com');
        $contactMethodEntity->setIsPrimary(false);

        $client = $this->mapper->toDomain($clientEntity, [$contactMethodEntity]);

        self::assertSame('Jane', $client->identity()->firstName);
        self::assertSame('Smith', $client->identity()->lastName);
        self::assertSame(ClientStatus::ARCHIVED, $client->status());
        self::assertCount(1, $client->contactMethods());
        self::assertTrue($client->contactMethods()[0]->isEmail());
        self::assertSame('jane@example.com', $client->contactMethods()[0]->value);
        self::assertFalse($client->contactMethods()[0]->isPrimary);
    }

    public function testToDomainMapsPostalAddress(): void
    {
        $clientId  = Uuid::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId  = Uuid::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');
        $updatedAt = new \DateTimeImmutable('2024-01-02 11:00:00');

        $clientEntity = new ClientEntity();
        $clientEntity->setId($clientId);
        $clientEntity->setClinicId($clinicId);
        $clientEntity->setFirstName('John');
        $clientEntity->setLastName('Doe');
        $clientEntity->setStatus(ClientStatus::ACTIVE);
        $clientEntity->setCreatedAt($createdAt);
        $clientEntity->setUpdatedAt($updatedAt);
        $clientEntity->setPostalAddress(new PostalAddressEmbeddable(
            streetLine1: '123 Main St',
            streetLine2: 'Apt 4B',
            postalCode: '75001',
            city: 'Paris',
            region: 'Île-de-France',
            countryCode: 'FR'
        ));

        $contactMethodEntity = new ContactMethodEntity();
        $contactMethodEntity->setId(Uuid::v7());
        $contactMethodEntity->setClientId($clientId);
        $contactMethodEntity->setType(ContactMethodType::PHONE);
        $contactMethodEntity->setLabel(ContactLabel::MOBILE);
        $contactMethodEntity->setValue('+33612345678');
        $contactMethodEntity->setIsPrimary(true);

        $client = $this->mapper->toDomain($clientEntity, [$contactMethodEntity]);

        self::assertNotNull($client->postalAddress());
        self::assertSame('123 Main St', $client->postalAddress()->streetLine1);
        self::assertSame('Apt 4B', $client->postalAddress()->streetLine2);
        self::assertSame('75001', $client->postalAddress()->postalCode);
        self::assertSame('Paris', $client->postalAddress()->city);
        self::assertSame('Île-de-France', $client->postalAddress()->region);
        self::assertSame('FR', $client->postalAddress()->countryCode);
    }

    public function testToDomainReturnsNullPostalAddressWhenEmpty(): void
    {
        $clientId  = Uuid::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId  = Uuid::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');

        $clientEntity = new ClientEntity();
        $clientEntity->setId($clientId);
        $clientEntity->setClinicId($clinicId);
        $clientEntity->setFirstName('John');
        $clientEntity->setLastName('Doe');
        $clientEntity->setStatus(ClientStatus::ACTIVE);
        $clientEntity->setCreatedAt($createdAt);
        $clientEntity->setUpdatedAt($createdAt);
        $clientEntity->setPostalAddress(new PostalAddressEmbeddable());

        $contactMethodEntity = new ContactMethodEntity();
        $contactMethodEntity->setId(Uuid::v7());
        $contactMethodEntity->setClientId($clientId);
        $contactMethodEntity->setType(ContactMethodType::PHONE);
        $contactMethodEntity->setLabel(ContactLabel::MOBILE);
        $contactMethodEntity->setValue('+33612345678');
        $contactMethodEntity->setIsPrimary(true);

        $client = $this->mapper->toDomain($clientEntity, [$contactMethodEntity]);

        self::assertNull($client->postalAddress());
    }

    public function testToEntityMapsDomainClientToEntity(): void
    {
        $clientId  = ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId  = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');
        $updatedAt = new \DateTimeImmutable('2024-01-02 11:00:00');

        $client = Client::reconstitute(
            id: $clientId,
            clinicId: $clinicId,
            identity: new ClientIdentity('John', 'Doe'),
            status: ClientStatus::ACTIVE,
            contactMethods: [
                ContactMethod::phone(PhoneNumber::fromString('+33612345678'), ContactLabel::MOBILE, true),
            ],
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );

        $mapped = $this->mapper->toEntity($client);

        self::assertArrayHasKey('client', $mapped);
        self::assertArrayHasKey('contactMethods', $mapped);
        self::assertInstanceOf(ClientEntity::class, $mapped['client']);
        self::assertCount(1, $mapped['contactMethods']);

        $clientEntity = $mapped['client'];
        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $clientEntity->getId()->toString());
        self::assertSame('12345678-9abc-def0-1234-56789abcdef0', $clientEntity->getClinicId()->toString());
        self::assertSame('John', $clientEntity->getFirstName());
        self::assertSame('Doe', $clientEntity->getLastName());
        self::assertSame(ClientStatus::ACTIVE, $clientEntity->getStatus());
        self::assertSame($createdAt, $clientEntity->getCreatedAt());
        self::assertSame($updatedAt, $clientEntity->getUpdatedAt());

        $contactMethodEntity = $mapped['contactMethods'][0];
        self::assertInstanceOf(ContactMethodEntity::class, $contactMethodEntity);
        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $contactMethodEntity->getClientId()->toString());
        self::assertSame(ContactMethodType::PHONE, $contactMethodEntity->getType());
        self::assertSame(ContactLabel::MOBILE, $contactMethodEntity->getLabel());
        self::assertSame('+33612345678', $contactMethodEntity->getValue());
        self::assertTrue($contactMethodEntity->isPrimary());
    }

    public function testToEntityMapsPostalAddress(): void
    {
        $clientId  = ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId  = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');

        $postalAddress = PostalAddress::create(
            streetLine1: '123 Main St',
            city: 'Paris',
            countryCode: 'FR',
            streetLine2: 'Apt 4B',
            postalCode: '75001',
            region: 'Île-de-France'
        );

        $client = Client::reconstitute(
            id: $clientId,
            clinicId: $clinicId,
            identity: new ClientIdentity('John', 'Doe'),
            status: ClientStatus::ACTIVE,
            contactMethods: [
                ContactMethod::email(EmailAddress::fromString('john@example.com'), ContactLabel::WORK, true),
            ],
            createdAt: $createdAt,
            updatedAt: $createdAt,
            postalAddress: $postalAddress
        );

        $mapped       = $this->mapper->toEntity($client);
        $clientEntity = $mapped['client'];
        $embeddable   = $clientEntity->getPostalAddress();

        self::assertSame('123 Main St', $embeddable->streetLine1);
        self::assertSame('Apt 4B', $embeddable->streetLine2);
        self::assertSame('75001', $embeddable->postalCode);
        self::assertSame('Paris', $embeddable->city);
        self::assertSame('Île-de-France', $embeddable->region);
        self::assertSame('FR', $embeddable->countryCode);
        self::assertFalse($embeddable->isEmpty());
    }

    public function testToEntityMapsNullPostalAddressToEmptyEmbeddable(): void
    {
        $clientId  = ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId  = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');

        $client = Client::reconstitute(
            id: $clientId,
            clinicId: $clinicId,
            identity: new ClientIdentity('John', 'Doe'),
            status: ClientStatus::ACTIVE,
            contactMethods: [
                ContactMethod::email(EmailAddress::fromString('john@example.com'), ContactLabel::WORK, true),
            ],
            createdAt: $createdAt,
            updatedAt: $createdAt,
            postalAddress: null
        );

        $mapped       = $this->mapper->toEntity($client);
        $clientEntity = $mapped['client'];
        $embeddable   = $clientEntity->getPostalAddress();

        self::assertTrue($embeddable->isEmpty());
        self::assertNull($embeddable->streetLine1);
        self::assertNull($embeddable->city);
        self::assertNull($embeddable->countryCode);
    }

    public function testToEntityMapsMultipleContactMethods(): void
    {
        $clientId  = ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId  = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');

        $client = Client::reconstitute(
            id: $clientId,
            clinicId: $clinicId,
            identity: new ClientIdentity('John', 'Doe'),
            status: ClientStatus::ACTIVE,
            contactMethods: [
                ContactMethod::phone(PhoneNumber::fromString('+33612345678'), ContactLabel::MOBILE, true),
                ContactMethod::email(EmailAddress::fromString('john@example.com'), ContactLabel::WORK, false),
            ],
            createdAt: $createdAt,
            updatedAt: $createdAt
        );

        $mapped = $this->mapper->toEntity($client);

        self::assertCount(2, $mapped['contactMethods']);
        self::assertSame(ContactMethodType::PHONE, $mapped['contactMethods'][0]->getType());
        self::assertSame(ContactMethodType::EMAIL, $mapped['contactMethods'][1]->getType());
    }
}
