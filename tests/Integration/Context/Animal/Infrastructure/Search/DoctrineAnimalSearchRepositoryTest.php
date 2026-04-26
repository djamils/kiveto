<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Animal\Infrastructure\Search;

use App\Context\Animal\Application\Search\AnimalSearchRepositoryInterface;
use App\Shared\Search\Query\SearchQuery;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DoctrineAnimalSearchRepositoryTest extends KernelTestCase
{
    private Connection $conn;
    private AnimalSearchRepositoryInterface $repo;
    private string $clinicId;
    private string $otherClinicId;

    protected function setUp(): void
    {
        self::bootKernel();

        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        \assert($em instanceof EntityManagerInterface);

        $repo = static::getContainer()->get(AnimalSearchRepositoryInterface::class);
        \assert($repo instanceof AnimalSearchRepositoryInterface);

        $this->conn          = $em->getConnection();
        $this->repo          = $repo;
        $this->clinicId      = Uuid::v7()->toString();
        $this->otherClinicId = Uuid::v7()->toString();
    }

    public function testSearchByNameReturnsHits(): void
    {
        $animalId = Uuid::v7()->toString();
        $this->insertAnimal($animalId, $this->clinicId, 'Rémi', 'remi', null, null, 'active');

        $query  = new SearchQuery('remi', $this->clinicId, 'user-1', 21);
        $result = $this->repo->findByQuery($query);

        self::assertCount(1, $result);
        self::assertSame($animalId, $result[0]->id);
    }

    public function testSearchByChipReturnsExactMatch(): void
    {
        $animalId = Uuid::v7()->toString();
        $this->insertAnimal($animalId, $this->clinicId, 'Rex', 'rex', '250269802120045', null, 'active');

        $query  = new SearchQuery('250269802120045', $this->clinicId, 'user-1', 21);
        $result = $this->repo->findByQuery($query);

        self::assertCount(1, $result);
        self::assertSame($animalId, $result[0]->id);
    }

    public function testSearchByPhoneReturnsExactMatch(): void
    {
        $animalId = Uuid::v7()->toString();
        $this->insertAnimal($animalId, $this->clinicId, 'Rex', 'rex', null, '+33612345678', 'active');

        $query  = new SearchQuery('0612345678', $this->clinicId, 'user-1', 21);
        $result = $this->repo->findByQuery($query);

        self::assertCount(1, $result);
        self::assertSame($animalId, $result[0]->id);
    }

    public function testSearchRespectsClinicIdIsolation(): void
    {
        $animalId = Uuid::v7()->toString();
        $this->insertAnimal($animalId, $this->otherClinicId, 'Buddy', 'buddy', null, null, 'active');

        $query  = new SearchQuery('buddy', $this->clinicId, 'user-1', 21);
        $result = $this->repo->findByQuery($query);

        self::assertCount(0, $result);
    }

    public function testArchivedAnimalsNotReturned(): void
    {
        $animalId = Uuid::v7()->toString();
        $this->insertAnimal($animalId, $this->clinicId, 'Ghost', 'ghost', null, null, 'archived');

        $query  = new SearchQuery('ghost', $this->clinicId, 'user-1', 21);
        $result = $this->repo->findByQuery($query);

        self::assertCount(0, $result);
    }

    public function testSearchByNameNormalizesAccents(): void
    {
        $animalId = Uuid::v7()->toString();
        $this->insertAnimal($animalId, $this->clinicId, 'Rémi', 'remi', null, null, 'active');

        $query  = new SearchQuery('RÉMI', $this->clinicId, 'user-1', 21);
        $result = $this->repo->findByQuery($query);

        self::assertCount(1, $result);
        self::assertSame('Rémi', $result[0]->animalName);
    }

    private function insertAnimal(
        string $id,
        string $clinicId,
        string $animalName,
        string $searchName,
        ?string $searchChip,
        ?string $searchPhone,
        string $status,
    ): void {
        $cols = 'id, clinic_id, animal_name, search_name, search_chip, search_phone, species,'
            . ' breed_name, search_owner_name, primary_owner_client_id, status, updated_at';
        $vals = ':id, :clinicId, :animalName, :searchName, :searchChip, :searchPhone, :species,'
            . ' NULL, NULL, NULL, :status, NOW()';

        $this->conn->executeStatement(
            "INSERT INTO animal__search_entries ({$cols}) VALUES ({$vals})",
            [
                'id'          => Uuid::fromString($id)->toBinary(),
                'clinicId'    => Uuid::fromString($clinicId)->toBinary(),
                'animalName'  => $animalName,
                'searchName'  => $searchName,
                'searchChip'  => $searchChip,
                'searchPhone' => $searchPhone,
                'species'     => 'dog',
                'status'      => $status,
            ],
        );
    }
}
