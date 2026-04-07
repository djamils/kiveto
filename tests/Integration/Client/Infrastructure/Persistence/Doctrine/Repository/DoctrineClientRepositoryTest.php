<?php

declare(strict_types=1);

namespace App\Tests\Integration\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Client\Domain\Client;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Client\Domain\ValueObject\ClientId;
use App\Client\Domain\ValueObject\ClientIdentity;
use App\Client\Domain\ValueObject\ClientStatus;
use App\Client\Domain\ValueObject\ContactLabel;
use App\Client\Domain\ValueObject\ContactMethod;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Fixtures\Client\Factory\ClientEntityFactory;
use App\Fixtures\Client\Factory\ContactMethodEntityFactory;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Shared\Domain\ValueObject\PostalAddress;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineClientRepositoryTest extends KernelTestCase
{
    use Factories;

    public function testFindReconstitutesClientFromDoctrineEntity(): void
    {
        $clientId = '01234567-89ab-cdef-0123-456789abcdef';
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        ClientEntityFactory::createOne([
            'id'        => Uuid::fromString($clientId),
            'clinicId'  => Uuid::fromString($clinicId),
            'firstName' => 'John',
            'lastName'  => 'Doe',
            'status'    => ClientStatus::ACTIVE,
        ]);

        ContactMethodEntityFactory::createOne([
            'clientId'  => Uuid::fromString($clientId),
            'type'      => \App\Client\Domain\ValueObject\ContactMethodType::PHONE,
            'label'     => ContactLabel::MOBILE,
            'value'     => '+33612345678',
            'isPrimary' => true,
        ]);

        /** @var ClientRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClientRepositoryInterface::class);

        $client = $repo->find(
            ClinicId::fromString($clinicId),
            ClientId::fromString($clientId)
        );

        self::assertNotNull($client);
        self::assertSame('John', $client->identity()->firstName);
        self::assertSame('Doe', $client->identity()->lastName);
        self::assertSame(ClientStatus::ACTIVE, $client->status());
        self::assertCount(1, $client->contactMethods());
        self::assertTrue($client->contactMethods()[0]->isPhone());
        self::assertSame('+33612345678', $client->contactMethods()[0]->value);
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        /** @var ClientRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClientRepositoryInterface::class);

        $client = $repo->find(
            ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0'),
            ClientId::fromString('ffffffff-ffff-ffff-ffff-ffffffffffff')
        );

        self::assertNull($client);
    }

    public function testFindReturnsNullWhenClinicIdMismatch(): void
    {
        $clientId = '01234567-89ab-cdef-0123-456789abcdef';
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        ClientEntityFactory::createOne([
            'id'       => Uuid::fromString($clientId),
            'clinicId' => Uuid::fromString($clinicId),
        ]);

        /** @var ClientRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClientRepositoryInterface::class);

        $client = $repo->find(
            ClinicId::fromString('ffffffff-ffff-ffff-ffff-ffffffffffff'),
            ClientId::fromString($clientId)
        );

        self::assertNull($client);
    }

    public function testGetReturnsClientWhenFound(): void
    {
        $clientId = '01234567-89ab-cdef-0123-456789abcdef';
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        ClientEntityFactory::createOne([
            'id'        => Uuid::fromString($clientId),
            'clinicId'  => Uuid::fromString($clinicId),
            'firstName' => 'Jane',
            'lastName'  => 'Smith',
        ]);

        ContactMethodEntityFactory::createOne([
            'clientId' => Uuid::fromString($clientId),
            'type'     => \App\Client\Domain\ValueObject\ContactMethodType::EMAIL,
            'value'    => 'jane@example.com',
        ]);

        /** @var ClientRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClientRepositoryInterface::class);

        $client = $repo->get(
            ClinicId::fromString($clinicId),
            ClientId::fromString($clientId)
        );

        self::assertSame('Jane', $client->identity()->firstName);
        self::assertSame('Smith', $client->identity()->lastName);
    }

    public function testGetThrowsExceptionWhenNotFound(): void
    {
        /** @var ClientRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClientRepositoryInterface::class);

        $this->expectException(\App\Client\Domain\Exception\ClientNotFoundException::class);
        $this->expectExceptionMessage('Client with ID "ffffffff-ffff-ffff-ffff-ffffffffffff" not found.');

        $repo->get(
            ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0'),
            ClientId::fromString('ffffffff-ffff-ffff-ffff-ffffffffffff')
        );
    }

    public function testSavePersistsNewClient(): void
    {
        $clientId  = ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId  = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');

        /** @var ClientRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClientRepositoryInterface::class);

        $client = Client::create(
            id: $clientId,
            clinicId: $clinicId,
            identity: new ClientIdentity('John', 'Doe'),
            contactMethods: [
                ContactMethod::email(EmailAddress::fromString('john@example.com'), ContactLabel::WORK, true),
            ],
            createdAt: $createdAt
        );

        $repo->save($client);

        $persisted = $repo->find($clinicId, $clientId);

        self::assertNotNull($persisted);
        self::assertSame('John', $persisted->identity()->firstName);
        self::assertSame('Doe', $persisted->identity()->lastName);
        self::assertSame(ClientStatus::ACTIVE, $persisted->status());
        self::assertCount(1, $persisted->contactMethods());
    }

    public function testSaveUpdatesExistingClient(): void
    {
        $clientId = '01234567-89ab-cdef-0123-456789abcdef';
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        ClientEntityFactory::createOne([
            'id'        => Uuid::fromString($clientId),
            'clinicId'  => Uuid::fromString($clinicId),
            'firstName' => 'Original',
            'lastName'  => 'Name',
            'status'    => ClientStatus::ACTIVE,
        ]);

        ContactMethodEntityFactory::createOne([
            'clientId' => Uuid::fromString($clientId),
            'type'     => \App\Client\Domain\ValueObject\ContactMethodType::EMAIL,
            'value'    => 'jane@example.com',
        ]);

        /** @var ClientRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClientRepositoryInterface::class);

        $client = $repo->find(
            ClinicId::fromString($clinicId),
            ClientId::fromString($clientId)
        );

        self::assertNotNull($client);

        $client->updateIdentity(
            new ClientIdentity('Updated', 'Name'),
            new \DateTimeImmutable('2024-01-02 10:00:00')
        );

        $repo->save($client);

        /** @var Registry $doctrine */
        $doctrine = static::getContainer()->get('doctrine');
        $doctrine->getManager()->clear();

        $updated = $repo->find(
            ClinicId::fromString($clinicId),
            ClientId::fromString($clientId)
        );

        self::assertNotNull($updated);
        self::assertSame('Updated', $updated->identity()->firstName);
        self::assertSame('Name', $updated->identity()->lastName);
    }

    public function testSavePersistsPostalAddress(): void
    {
        $clientId  = ClientId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId  = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');

        /** @var ClientRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClientRepositoryInterface::class);

        $postalAddress = PostalAddress::create(
            streetLine1: '123 Main St',
            city: 'Paris',
            countryCode: 'FR',
            postalCode: '75001'
        );

        $client = Client::create(
            id: $clientId,
            clinicId: $clinicId,
            identity: new ClientIdentity('John', 'Doe'),
            contactMethods: [
                ContactMethod::phone(PhoneNumber::fromString('+33612345678'), ContactLabel::MOBILE, true),
            ],
            createdAt: $createdAt
        );

        $client->updatePostalAddress($postalAddress, $createdAt);

        $repo->save($client);

        $persisted = $repo->find($clinicId, $clientId);

        self::assertNotNull($persisted);
        self::assertNotNull($persisted->postalAddress());
        self::assertSame('123 Main St', $persisted->postalAddress()->streetLine1);
        self::assertSame('Paris', $persisted->postalAddress()->city);
        self::assertSame('FR', $persisted->postalAddress()->countryCode);
        self::assertSame('75001', $persisted->postalAddress()->postalCode);
    }

    public function testSaveReplacesContactMethods(): void
    {
        $clientId = '01234567-89ab-cdef-0123-456789abcdef';
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        ClientEntityFactory::createOne([
            'id'       => Uuid::fromString($clientId),
            'clinicId' => Uuid::fromString($clinicId),
            'status'   => ClientStatus::ACTIVE,
        ]);

        ContactMethodEntityFactory::createOne([
            'clientId' => Uuid::fromString($clientId),
            'type'     => \App\Client\Domain\ValueObject\ContactMethodType::EMAIL,
            'value'    => 'jane@example.com',
        ]);
        ContactMethodEntityFactory::createOne([
            'clientId' => Uuid::fromString($clientId),
            'type'     => \App\Client\Domain\ValueObject\ContactMethodType::EMAIL,
            'value'    => 'jane@example.com',
        ]);

        /** @var ClientRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClientRepositoryInterface::class);

        $client = $repo->find(
            ClinicId::fromString($clinicId),
            ClientId::fromString($clientId)
        );

        self::assertNotNull($client);
        self::assertCount(2, $client->contactMethods());

        $client->replaceContactMethods(
            [ContactMethod::email(EmailAddress::fromString('new@example.com'), ContactLabel::WORK, true)],
            new \DateTimeImmutable('2024-01-02 10:00:00')
        );

        $repo->save($client);

        /** @var Registry $doctrine */
        $doctrine = static::getContainer()->get('doctrine');
        $doctrine->getManager()->clear();

        $updated = $repo->find(
            ClinicId::fromString($clinicId),
            ClientId::fromString($clientId)
        );

        self::assertNotNull($updated);
        self::assertCount(1, $updated->contactMethods());
        self::assertSame('new@example.com', $updated->contactMethods()[0]->value);
    }
}
