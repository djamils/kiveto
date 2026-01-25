<?php

declare(strict_types=1);

namespace App\Tests\Integration\Animal\Infrastructure\Persistence\Doctrine\Repository;

use App\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Animal\Application\Query\SearchAnimals\SearchAnimalsCriteria;
use App\Animal\Domain\ValueObject\AnimalStatus;
use App\Animal\Domain\ValueObject\LifeStatus;
use App\Animal\Domain\ValueObject\Species;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Fixtures\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Animal\Factory\OwnershipEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DoctrineAnimalReadRepositoryTest extends KernelTestCase
{
    use ResetDatabase;

    public function testFindByIdReturnsAnimalView(): void
    {
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';
        $animalId = Uuid::v7();
        $clientId = Uuid::v7();

        AnimalEntityFactory::createOne([
            'id'       => $animalId,
            'clinicId' => Uuid::fromString($clinicId),
            'name'     => 'Rex',
            'species'  => Species::DOG,
        ]);

        OwnershipEntityFactory::createOne([
            'animal'   => AnimalEntityFactory::find(['id' => $animalId]),
            'clientId' => $clientId,
        ]);

        /* @phpstan-ignore-next-line method.nonObject, method.notFound */
        static::getContainer()->get('doctrine')->getManager()->flush();

        /** @var AnimalReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(AnimalReadRepositoryInterface::class);

        $view = $repo->findById(
            ClinicId::fromString($clinicId),
            \App\Animal\Domain\ValueObject\AnimalId::fromString($animalId->toString())
        );

        self::assertNotNull($view);
        self::assertSame('Rex', $view->name);
        self::assertSame('dog', $view->species);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $animalId = \App\Animal\Domain\ValueObject\AnimalId::fromString(Uuid::v7()->toString());

        /** @var AnimalReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(AnimalReadRepositoryInterface::class);

        $view = $repo->findById($clinicId, $animalId);

        self::assertNull($view);
    }

    public function testSearchReturnsAnimals(): void
    {
        $clinicId   = '12345678-9abc-def0-1234-56789abcdef0';
        $clinicUuid = Uuid::fromString($clinicId);

        AnimalEntityFactory::createOne([
            'clinicId' => $clinicUuid,
            'name'     => 'Rex',
            'species'  => Species::DOG,
            'status'   => AnimalStatus::ACTIVE,
        ]);

        AnimalEntityFactory::createOne([
            'clinicId' => $clinicUuid,
            'name'     => 'Max',
            'species'  => Species::CAT,
            'status'   => AnimalStatus::ACTIVE,
        ]);

        /* @phpstan-ignore-next-line method.nonObject, method.notFound */
        static::getContainer()->get('doctrine')->getManager()->flush();

        /** @var AnimalReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(AnimalReadRepositoryInterface::class);

        $criteria = new SearchAnimalsCriteria();
        $result   = $repo->search(ClinicId::fromString($clinicId), $criteria);

        self::assertArrayHasKey('items', $result);
        self::assertArrayHasKey('total', $result);
        self::assertCount(2, $result['items']);
        self::assertSame(2, $result['total']);
    }

    public function testSearchFiltersByStatus(): void
    {
        $clinicId   = '12345678-9abc-def0-1234-56789abcdef0';
        $clinicUuid = Uuid::fromString($clinicId);

        AnimalEntityFactory::createOne([
            'clinicId' => $clinicUuid,
            'name'     => 'Rex',
            'status'   => AnimalStatus::ACTIVE,
        ]);

        AnimalEntityFactory::createOne([
            'clinicId' => $clinicUuid,
            'name'     => 'Max',
            'status'   => AnimalStatus::ARCHIVED,
        ]);

        /* @phpstan-ignore-next-line method.nonObject, method.notFound */
        static::getContainer()->get('doctrine')->getManager()->flush();

        /** @var AnimalReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(AnimalReadRepositoryInterface::class);

        $criteria = new SearchAnimalsCriteria(status: 'active');
        $result   = $repo->search(ClinicId::fromString($clinicId), $criteria);

        self::assertCount(1, $result['items']);
        self::assertSame(1, $result['total']);
        self::assertSame('Rex', $result['items'][0]->name);
    }

    public function testSearchFiltersBySearchTerm(): void
    {
        $clinicId   = '12345678-9abc-def0-1234-56789abcdef0';
        $clinicUuid = Uuid::fromString($clinicId);

        AnimalEntityFactory::createOne([
            'clinicId' => $clinicUuid,
            'name'     => 'Rex',
        ]);

        AnimalEntityFactory::createOne([
            'clinicId' => $clinicUuid,
            'name'     => 'Max',
        ]);

        /* @phpstan-ignore-next-line method.nonObject, method.notFound */
        static::getContainer()->get('doctrine')->getManager()->flush();

        /** @var AnimalReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(AnimalReadRepositoryInterface::class);

        $criteria = new SearchAnimalsCriteria(searchTerm: 'Rex');
        $result   = $repo->search(ClinicId::fromString($clinicId), $criteria);

        self::assertCount(1, $result['items']);
        self::assertSame('Rex', $result['items'][0]->name);
    }

    public function testSearchFiltersBySpecies(): void
    {
        $clinicId   = '12345678-9abc-def0-1234-56789abcdef0';
        $clinicUuid = Uuid::fromString($clinicId);

        AnimalEntityFactory::createOne([
            'clinicId' => $clinicUuid,
            'species'  => Species::DOG,
        ]);

        AnimalEntityFactory::createOne([
            'clinicId' => $clinicUuid,
            'species'  => Species::CAT,
        ]);

        /* @phpstan-ignore-next-line method.nonObject, method.notFound */
        static::getContainer()->get('doctrine')->getManager()->flush();

        /** @var AnimalReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(AnimalReadRepositoryInterface::class);

        $criteria = new SearchAnimalsCriteria(species: 'dog');
        $result   = $repo->search(ClinicId::fromString($clinicId), $criteria);

        self::assertCount(1, $result['items']);
    }

    public function testSearchFiltersByLifeStatus(): void
    {
        $clinicId   = '12345678-9abc-def0-1234-56789abcdef0';
        $clinicUuid = Uuid::fromString($clinicId);

        AnimalEntityFactory::createOne([
            'clinicId'   => $clinicUuid,
            'lifeStatus' => LifeStatus::ALIVE,
        ]);

        AnimalEntityFactory::createOne([
            'clinicId'   => $clinicUuid,
            'lifeStatus' => LifeStatus::DECEASED,
            'deceasedAt' => new \DateTimeImmutable(),
        ]);

        /* @phpstan-ignore-next-line method.nonObject, method.notFound */
        static::getContainer()->get('doctrine')->getManager()->flush();

        /** @var AnimalReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(AnimalReadRepositoryInterface::class);

        $criteria = new SearchAnimalsCriteria(lifeStatus: 'alive');
        $result   = $repo->search(ClinicId::fromString($clinicId), $criteria);

        self::assertCount(1, $result['items']);
    }

    public function testSearchFiltersByOwnerClientId(): void
    {
        $clinicId   = '12345678-9abc-def0-1234-56789abcdef0';
        $clinicUuid = Uuid::fromString($clinicId);
        $clientId1  = Uuid::v7();
        $clientId2  = Uuid::v7();

        $animal1 = AnimalEntityFactory::createOne([
            'clinicId' => $clinicUuid,
        ]);

        $animal2 = AnimalEntityFactory::createOne([
            'clinicId' => $clinicUuid,
        ]);

        OwnershipEntityFactory::createOne([
            'animal'   => $animal1,
            'clientId' => $clientId1,
        ]);

        OwnershipEntityFactory::createOne([
            'animal'   => $animal2,
            'clientId' => $clientId2,
        ]);

        /* @phpstan-ignore-next-line method.nonObject, method.notFound */
        static::getContainer()->get('doctrine')->getManager()->flush();

        /** @var AnimalReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(AnimalReadRepositoryInterface::class);

        $criteria = new SearchAnimalsCriteria(ownerClientId: $clientId1->toString());
        $result   = $repo->search(ClinicId::fromString($clinicId), $criteria);

        self::assertCount(1, $result['items']);
    }

    public function testSearchPagination(): void
    {
        $clinicId   = '12345678-9abc-def0-1234-56789abcdef0';
        $clinicUuid = Uuid::fromString($clinicId);

        for ($i = 0; $i < 25; ++$i) {
            AnimalEntityFactory::createOne([
                'clinicId' => $clinicUuid,
                'name'     => 'Animal ' . $i,
            ]);
        }

        /* @phpstan-ignore-next-line method.nonObject, method.notFound */
        static::getContainer()->get('doctrine')->getManager()->flush();

        /** @var AnimalReadRepositoryInterface $repo */
        $repo = static::getContainer()->get(AnimalReadRepositoryInterface::class);

        $criteria = new SearchAnimalsCriteria(page: 1, limit: 10);
        $result   = $repo->search(ClinicId::fromString($clinicId), $criteria);

        self::assertCount(10, $result['items']);
        self::assertSame(25, $result['total']);

        $criteria2 = new SearchAnimalsCriteria(page: 3, limit: 10);
        $result2   = $repo->search(ClinicId::fromString($clinicId), $criteria2);

        self::assertCount(5, $result2['items']);
        self::assertSame(25, $result2['total']);
    }
}
