<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Search;

use App\Context\Client\Application\Search\ClientSearchRepositoryInterface;
use App\Context\Client\Application\Search\ClientSearchRow;
use App\Shared\Search\Normalization\SearchTermNormalizer;
use App\Shared\Search\Query\SearchQuery;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineClientSearchRepository implements ClientSearchRepositoryInterface
{
    private const string SELECT = 'SELECT BIN_TO_UUID(id) AS id, first_name, last_name, search_phone, primary_email, status'
        . ' FROM client_search_index';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SearchTermNormalizer $normalizer,
    ) {
    }

    public function findByQuery(SearchQuery $query): array
    {
        $conn         = $this->entityManager->getConnection();
        $clinicBinary = Uuid::fromString($query->clinicId)->toBinary();

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
     * @return list<ClientSearchRow>
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
     * @return list<ClientSearchRow>
     */
    private function searchByName(Connection $conn, string $normalizedTerm, string $clinicBinary, int $limit): array
    {
        $rows = $conn->fetchAllAssociative(
            self::SELECT . ' WHERE clinic_id = :clinicId AND search_name LIKE :prefix AND status = :status LIMIT :limit',
            ['clinicId' => $clinicBinary, 'prefix' => $normalizedTerm . '%', 'status' => 'active', 'limit' => $limit],
            ['limit'    => ParameterType::INTEGER],
        );

        return $this->mapRows($rows);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<ClientSearchRow>
     */
    private function mapRows(array $rows): array
    {
        $result = [];

        foreach ($rows as $row) {
            \assert(\is_string($row['id']));
            \assert(\is_string($row['first_name']));
            \assert(\is_string($row['last_name']));
            \assert(\is_string($row['status']));

            $result[] = new ClientSearchRow(
                id: $row['id'],
                firstName: $row['first_name'],
                lastName: $row['last_name'],
                searchPhone: isset($row['search_phone']) && \is_string($row['search_phone'])
                    ? $row['search_phone']
                    : null,
                primaryEmail: isset($row['primary_email']) && \is_string($row['primary_email'])
                    ? $row['primary_email']
                    : null,
                status: $row['status'],
            );
        }

        return $result;
    }
}
