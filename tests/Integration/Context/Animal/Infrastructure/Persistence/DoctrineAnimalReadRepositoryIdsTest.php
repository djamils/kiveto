<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Animal\Infrastructure\Persistence;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Covers the identifier lookup other contexts use to filter their own lists by
 * animal, owner or species.
 */
final class DoctrineAnimalReadRepositoryIdsTest extends KernelTestCase
{
    private const string CLINIC_ID       = 'c1c1c1c1-c1c1-4c1c-8c1c-c1c1c1c1c1c1';
    private const string OTHER_CLINIC_ID = 'c2c2c2c2-c2c2-4c2c-8c2c-c2c2c2c2c2c2';

    private const string LUNA   = 'a1a1a1a1-0000-4000-8000-000000000001';
    private const string MINOU  = 'a1a1a1a1-0000-4000-8000-000000000002';
    private const string BUNNY  = 'a1a1a1a1-0000-4000-8000-000000000003';
    private const string ABROAD = 'a1a1a1a1-0000-4000-8000-000000000004';

    private Connection $connection;

    private AnimalReadRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        \assert($entityManager instanceof EntityManagerInterface);
        $this->connection = $entityManager->getConnection();

        $repository = self::getContainer()->get(AnimalReadRepositoryInterface::class);
        \assert($repository instanceof AnimalReadRepositoryInterface);
        $this->repository = $repository;

        $this->insert(self::LUNA, self::CLINIC_ID, 'Luna', 'dog', 'Beauceron', 'Marie Lambert');
        $this->insert(self::MINOU, self::CLINIC_ID, 'Minou', 'cat', 'Européen', 'Sophie Petit');
        $this->insert(self::BUNNY, self::CLINIC_ID, 'Caramel', 'nac', 'Nain bélier', 'Marie Lambert');
        $this->insert(self::ABROAD, self::OTHER_CLINIC_ID, 'Luna', 'dog', 'Beauceron', 'Marie Lambert');
    }

    public function testWithoutFilterItReturnsEveryAnimalOfTheClinic(): void
    {
        self::assertSame(
            [self::LUNA, self::MINOU, self::BUNNY],
            $this->findIds(null, null),
        );
    }

    public function testAnEmptyTermAndSpeciesBehaveLikeNoFilter(): void
    {
        self::assertSame(
            [self::LUNA, self::MINOU, self::BUNNY],
            $this->findIds('', ''),
        );
    }

    public function testFilterBySpecies(): void
    {
        self::assertSame([self::MINOU], $this->findIds(null, 'cat'));
    }

    public function testTermMatchesTheAnimalName(): void
    {
        self::assertSame([self::MINOU], $this->findIds('Minou', null));
    }

    public function testTermMatchesTheOwnerName(): void
    {
        self::assertSame([self::LUNA, self::BUNNY], $this->findIds('Lambert', null));
    }

    public function testTermMatchesTheBreed(): void
    {
        self::assertSame([self::BUNNY], $this->findIds('bélier', null));
    }

    public function testTermAndSpeciesCombine(): void
    {
        self::assertSame([self::LUNA], $this->findIds('Lambert', 'dog'));
    }

    public function testAnotherClinicIsNeverReturned(): void
    {
        self::assertNotContains(self::ABROAD, $this->findIds('Luna', null));
    }

    public function testAnUnmatchedTermReturnsNothing(): void
    {
        self::assertSame([], $this->findIds('inexistant', null));
    }

    /**
     * Sorted, because callers only ever use the result as a set.
     *
     * @return list<string>
     */
    private function findIds(?string $searchTerm, ?string $species): array
    {
        $ids = $this->repository->findIdsMatching(
            ClinicId::fromString(self::CLINIC_ID),
            $searchTerm,
            $species,
        );

        sort($ids);

        return $ids;
    }

    private function insert(
        string $id,
        string $clinicId,
        string $name,
        string $species,
        string $breed,
        string $ownerName,
    ): void {
        $this->connection->executeStatement(
            'INSERT INTO animal__search_entries
                (id, clinic_id, animal_name, search_name, search_chip, search_phone,
                 species, breed_name, search_owner_name, primary_owner_client_id, status, updated_at)
             VALUES (:id, :clinicId, :name, :searchName, NULL, NULL,
                 :species, :breed, :ownerName, NULL, :status, NOW())',
            [
                'id'         => Uuid::fromString($id)->toBinary(),
                'clinicId'   => Uuid::fromString($clinicId)->toBinary(),
                'name'       => $name,
                'searchName' => mb_strtolower($name),
                'species'    => $species,
                'breed'      => $breed,
                'ownerName'  => $ownerName,
                'status'     => 'active',
            ],
        );
    }
}
