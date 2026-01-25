<?php

declare(strict_types=1);

namespace App\Tests\Integration\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Client\Application\Port\ClientReadRepositoryInterface;
use App\Client\Application\Query\SearchClients\SearchClientsCriteria;
use App\Client\Domain\ValueObject\ClientId;
use App\Client\Domain\ValueObject\ClientStatus;
use App\Client\Domain\ValueObject\ContactLabel;
use App\Client\Domain\ValueObject\ContactMethodType;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Fixtures\Client\Factory\ClientEntityFactory;
use App\Fixtures\Client\Factory\ContactMethodEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineClientReadRepositoryTest extends KernelTestCase
{
    use Factories;

    public function testFindByIdReturnsClientView(): void
    {
        $clientId = '01234567-89ab-cdef-0123-456789abcdef';
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        ClientEntityFactory::new()
            ->withId($clientId)
            ->withClinicId($clinicId)
            ->withName('John', 'Doe')
            ->active()
            ->withPostalAddress(
                streetLine1: '123 Main St',
                city: 'Paris',
                countryCode: 'FR',
                postalCode: '75001'
            )
            ->create()
        ;

        ContactMethodEntityFactory::createOne([
            'clientId'  => Uuid::fromString($clientId),
            'type'      => ContactMethodType::PHONE,
            'label'     => ContactLabel::MOBILE,
            'value'     => '+33612345678',
            'isPrimary' => true,
        ]);

        /** @var ClientReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClientReadRepositoryInterface::class);

        $view = $repo->findById(
            ClinicId::fromString($clinicId),
            ClientId::fromString($clientId)
        );

        self::assertNotNull($view);
        self::assertSame($clientId, $view->id);
        self::assertSame($clinicId, $view->clinicId);
        self::assertSame('John', $view->firstName);
        self::assertSame('Doe', $view->lastName);
        self::assertSame('active', $view->status);
        self::assertCount(1, $view->contactMethods);
        self::assertSame('phone', $view->contactMethods[0]->type);
        self::assertSame('mobile', $view->contactMethods[0]->label);
        self::assertSame('+33612345678', $view->contactMethods[0]->value);
        self::assertTrue($view->contactMethods[0]->isPrimary);
        self::assertNotNull($view->postalAddress);
        self::assertSame('123 Main St', $view->postalAddress->streetLine1);
        self::assertSame('Paris', $view->postalAddress->city);
        self::assertSame('FR', $view->postalAddress->countryCode);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        /** @var ClientReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClientReadRepositoryInterface::class);

        $view = $repo->findById(
            ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0'),
            ClientId::fromString('ffffffff-ffff-ffff-ffff-ffffffffffff')
        );

        self::assertNull($view);
    }

    public function testFindByIdReturnsNullPostalAddressWhenEmpty(): void
    {
        $clientId = '01234567-89ab-cdef-0123-456789abcdef';
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        ClientEntityFactory::createOne([
            'id'       => Uuid::fromString($clientId),
            'clinicId' => Uuid::fromString($clinicId),
        ]);

        ContactMethodEntityFactory::createOne([
            'clientId' => Uuid::fromString($clientId),
            'type'     => ContactMethodType::PHONE,
            'value'    => '+33612345678',
        ]);

        /** @var ClientReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClientReadRepositoryInterface::class);

        $view = $repo->findById(
            ClinicId::fromString($clinicId),
            ClientId::fromString($clientId)
        );

        self::assertNotNull($view);
        self::assertNull($view->postalAddress);
    }

    public function testSearchReturnsAllClientsForClinic(): void
    {
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        ClientEntityFactory::createOne([
            'clinicId'  => Uuid::fromString($clinicId),
            'firstName' => 'John',
            'lastName'  => 'Doe',
        ]);

        ClientEntityFactory::createOne([
            'clinicId'  => Uuid::fromString($clinicId),
            'firstName' => 'Jane',
            'lastName'  => 'Smith',
        ]);

        ClientEntityFactory::createOne([
            'clinicId' => Uuid::v7(), // Different clinic
        ]);

        /** @var ClientReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClientReadRepositoryInterface::class);

        $criteria = new SearchClientsCriteria();
        $result   = $repo->search(ClinicId::fromString($clinicId), $criteria);

        self::assertArrayHasKey('items', $result);
        self::assertArrayHasKey('total', $result);
        self::assertCount(2, $result['items']);
        self::assertSame(2, $result['total']);
    }

    public function testSearchFiltersByStatus(): void
    {
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        ClientEntityFactory::createOne([
            'clinicId' => Uuid::fromString($clinicId),
            'status'   => ClientStatus::ACTIVE,
        ]);

        ClientEntityFactory::createOne([
            'clinicId' => Uuid::fromString($clinicId),
            'status'   => ClientStatus::ACTIVE,
        ]);

        ClientEntityFactory::createOne([
            'clinicId' => Uuid::fromString($clinicId),
            'status'   => ClientStatus::ARCHIVED,
        ]);

        /** @var ClientReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClientReadRepositoryInterface::class);

        $criteria = new SearchClientsCriteria(status: 'active');
        $result   = $repo->search(ClinicId::fromString($clinicId), $criteria);

        self::assertCount(2, $result['items']);
        self::assertSame('active', $result['items'][0]->status);
        self::assertSame('active', $result['items'][1]->status);
    }

    public function testSearchFiltersBySearchTerm(): void
    {
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        ClientEntityFactory::createOne([
            'clinicId'  => Uuid::fromString($clinicId),
            'firstName' => 'John',
            'lastName'  => 'Doe',
        ]);

        ClientEntityFactory::createOne([
            'clinicId'  => Uuid::fromString($clinicId),
            'firstName' => 'Jane',
            'lastName'  => 'Smith',
        ]);

        ClientEntityFactory::createOne([
            'clinicId'  => Uuid::fromString($clinicId),
            'firstName' => 'Bob',
            'lastName'  => 'Johnson',
        ]);

        /** @var ClientReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClientReadRepositoryInterface::class);

        $criteria = new SearchClientsCriteria(searchTerm: 'John');
        $result   = $repo->search(ClinicId::fromString($clinicId), $criteria);

        self::assertCount(2, $result['items']); // John Doe + Bob Johnson
    }

    public function testSearchCombinesFilters(): void
    {
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        ClientEntityFactory::createOne([
            'clinicId'  => Uuid::fromString($clinicId),
            'firstName' => 'John',
            'lastName'  => 'Doe',
            'status'    => ClientStatus::ACTIVE,
        ]);

        ClientEntityFactory::createOne([
            'clinicId'  => Uuid::fromString($clinicId),
            'firstName' => 'John',
            'lastName'  => 'Smith',
            'status'    => ClientStatus::ARCHIVED,
        ]);

        /** @var ClientReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClientReadRepositoryInterface::class);

        $criteria = new SearchClientsCriteria(searchTerm: 'John', status: 'active');
        $result   = $repo->search(ClinicId::fromString($clinicId), $criteria);

        self::assertCount(1, $result['items']);
        self::assertSame('John', $result['items'][0]->firstName);
        self::assertSame('Doe', $result['items'][0]->lastName);
        self::assertSame('active', $result['items'][0]->status);
    }

    public function testSearchPaginatesResults(): void
    {
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        for ($i = 1; $i <= 25; ++$i) {
            ClientEntityFactory::createOne([
                'clinicId'  => Uuid::fromString($clinicId),
                'firstName' => 'Client',
                'lastName'  => (string) $i,
            ]);
        }

        /** @var ClientReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClientReadRepositoryInterface::class);

        $criteriaPage1 = new SearchClientsCriteria(page: 1, limit: 10);
        $resultPage1   = $repo->search(ClinicId::fromString($clinicId), $criteriaPage1);

        self::assertCount(10, $resultPage1['items']);
        self::assertSame(25, $resultPage1['total']);

        $criteriaPage3 = new SearchClientsCriteria(page: 3, limit: 10);
        $resultPage3   = $repo->search(ClinicId::fromString($clinicId), $criteriaPage3);

        self::assertCount(5, $resultPage3['items']);
        self::assertSame(25, $resultPage3['total']);
    }

    public function testSearchOrdersByLastNameThenFirstName(): void
    {
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';

        ClientEntityFactory::createOne([
            'clinicId'  => Uuid::fromString($clinicId),
            'firstName' => 'Charlie',
            'lastName'  => 'Anderson',
        ]);

        ClientEntityFactory::createOne([
            'clinicId'  => Uuid::fromString($clinicId),
            'firstName' => 'Alice',
            'lastName'  => 'Anderson',
        ]);

        ClientEntityFactory::createOne([
            'clinicId'  => Uuid::fromString($clinicId),
            'firstName' => 'Bob',
            'lastName'  => 'Zimmerman',
        ]);

        /** @var ClientReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(ClientReadRepositoryInterface::class);

        $criteria = new SearchClientsCriteria();
        $result   = $repo->search(ClinicId::fromString($clinicId), $criteria);

        self::assertCount(3, $result['items']);
        self::assertSame('Alice', $result['items'][0]->firstName);
        self::assertSame('Anderson', $result['items'][0]->lastName);
        self::assertSame('Charlie', $result['items'][1]->firstName);
        self::assertSame('Anderson', $result['items'][1]->lastName);
        self::assertSame('Bob', $result['items'][2]->firstName);
        self::assertSame('Zimmerman', $result['items'][2]->lastName);
    }
}
