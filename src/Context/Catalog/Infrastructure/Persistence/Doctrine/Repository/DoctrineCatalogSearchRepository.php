<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Catalog\Application\Port\CatalogSearchRepositoryInterface;
use App\Context\Catalog\Application\Query\Catalog\SearchCatalogItems\CatalogSearchResult;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineCatalogSearchRepository implements CatalogSearchRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return list<CatalogSearchResult> */
    public function search(string $term, ClinicId $clinicId, int $limit): array
    {
        $tenantId  = Uuid::fromString($clinicId->toString())->toBinary();
        $pattern   = '%' . $term . '%';
        $halfLimit = (int) ceil($limit / 2);

        $actSql = \sprintf(
            "SELECT BIN_TO_UUID(id) AS id, 'ACT' AS type, name, code, status
            FROM catalog__acts
            WHERE tenant_id = :tenantId AND name LIKE :pattern
            LIMIT %d",
            $halfLimit,
        );

        $articleSql = \sprintf(
            "SELECT BIN_TO_UUID(id) AS id, 'ARTICLE' AS type, name, code, status
            FROM catalog__articles
            WHERE tenant_id = :tenantId AND name LIKE :pattern
            LIMIT %d",
            $halfLimit,
        );

        $params = ['tenantId' => $tenantId, 'pattern' => $pattern];
        $types  = ['tenantId' => ParameterType::BINARY, 'pattern' => ParameterType::STRING];

        $actRows     = $this->connection->fetchAllAssociative($actSql, $params, $types);
        $articleRows = $this->connection->fetchAllAssociative($articleSql, $params, $types);

        $results = [];

        foreach (array_merge($actRows, $articleRows) as $row) {
            $results[] = new CatalogSearchResult(
                id: \is_string($row['id'] ?? null) ? $row['id'] : '',
                type: \is_string($row['type'] ?? null) ? $row['type'] : '',
                name: \is_string($row['name'] ?? null) ? $row['name'] : '',
                code: \is_string($row['code'] ?? null) ? $row['code'] : '',
                status: \is_string($row['status'] ?? null) ? $row['status'] : '',
            );
        }

        return \array_slice($results, 0, $limit);
    }
}
