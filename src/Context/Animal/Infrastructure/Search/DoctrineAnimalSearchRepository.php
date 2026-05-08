<?php

declare(strict_types=1);

namespace App\Context\Animal\Infrastructure\Search;

use App\Context\Animal\Application\Search\AnimalSearchRepositoryInterface;
use App\Context\Animal\Application\Search\AnimalSearchRow;
use App\Shared\Search\Normalization\SearchTermNormalizer;
use App\Shared\Search\Query\SearchQuery;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAnimalSearchRepository implements AnimalSearchRepositoryInterface
{
    private const string SELECT = 'SELECT BIN_TO_UUID(id) AS id, animal_name, species, breed_name,'
        . ' search_owner_name, BIN_TO_UUID(primary_owner_client_id) AS primary_owner_client_id, status'
        . ' FROM animal__search_entries';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SearchTermNormalizer $normalizer,
    ) {
    }

    public function findByQuery(SearchQuery $query): array
    {
        $conn         = $this->entityManager->getConnection();
        $clinicBinary = Uuid::fromString($query->clinicId)->toBinary();

        if ($this->normalizer->isChipNumber($query->term)) {
            return $this->searchByChip($conn, $query->term, $clinicBinary, $query->limit);
        }

        if ($this->normalizer->isPhone($query->term)) {
            $phone = $this->normalizer->normalizePhone($query->term);

            if (null === $phone) {
                return [];
            }

            return $this->searchByPhone($conn, $phone, $clinicBinary, $query->limit);
        }

        return $this->searchByName($conn, $query->normalizedTerm(), $clinicBinary, $query->limit);
    }

    /**
     * @return list<AnimalSearchRow>
     */
    private function searchByChip(Connection $conn, string $chip, string $clinicBinary, int $limit): array
    {
        $rows = $conn->fetchAllAssociative(
            self::SELECT . ' WHERE clinic_id = :clinicId AND search_chip = :chip AND status = :status LIMIT :limit',
            ['clinicId' => $clinicBinary, 'chip' => $chip, 'status' => 'active', 'limit' => $limit],
            ['limit'    => ParameterType::INTEGER],
        );

        return $this->mapRows($rows);
    }

    /**
     * @return list<AnimalSearchRow>
     */
    private function searchByPhone(Connection $conn, string $phone, string $clinicBinary, int $limit): array
    {
        $rows = $conn->fetchAllAssociative(
            self::SELECT . ' WHERE clinic_id = :clinicId AND search_phone = :phone AND status = :status LIMIT :limit',
            ['clinicId' => $clinicBinary, 'phone' => $phone, 'status' => 'active', 'limit' => $limit],
            ['limit'    => ParameterType::INTEGER],
        );

        return $this->mapRows($rows);
    }

    /**
     * @return list<AnimalSearchRow>
     */
    private function searchByName(Connection $conn, string $normalizedTerm, string $clinicBinary, int $limit): array
    {
        $rows = $conn->fetchAllAssociative(
            self::SELECT . ' WHERE clinic_id = :clinicId'
                . ' AND (search_name LIKE :prefix OR LOWER(search_owner_name) LIKE :ownerContains)'
                . ' AND status = :status LIMIT :limit',
            [
                'clinicId'      => $clinicBinary,
                'prefix'        => $normalizedTerm . '%',
                'ownerContains' => '%' . $normalizedTerm . '%',
                'status'        => 'active',
                'limit'         => $limit,
            ],
            ['limit' => ParameterType::INTEGER],
        );

        return $this->mapRows($rows);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<AnimalSearchRow>
     */
    private function mapRows(array $rows): array
    {
        $result = [];

        foreach ($rows as $row) {
            \assert(\is_string($row['id']));
            \assert(\is_string($row['animal_name']));
            \assert(\is_string($row['species']));
            \assert(\is_string($row['status']));

            $result[] = new AnimalSearchRow(
                id: $row['id'],
                animalName: $row['animal_name'],
                species: $row['species'],
                breedName: isset($row['breed_name']) && \is_string($row['breed_name'])
                    ? $row['breed_name']
                    : null,
                searchOwnerName: isset($row['search_owner_name']) && \is_string($row['search_owner_name'])
                    ? $row['search_owner_name']
                    : null,
                primaryOwnerClientId: isset($row['primary_owner_client_id'])
                    && \is_string($row['primary_owner_client_id'])
                    ? $row['primary_owner_client_id']
                    : null,
                status: $row['status'],
            );
        }

        return $result;
    }
}
