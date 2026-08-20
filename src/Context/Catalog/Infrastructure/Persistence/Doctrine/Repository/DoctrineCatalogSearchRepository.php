<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Catalog\Application\Port\CatalogSearchRepositoryInterface;
use App\Context\Catalog\Application\Query\Catalog\SearchCatalogItems\CatalogSearchResult;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Infrastructure\Persistence\RowAccessor;
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

        // Price, tax category and the prescription flag come from the same row:
        // consumers (the consultation cockpit) must never need a per-result
        // detail query to know whether an item is billable or requires an Rx.
        $actSql = \sprintf(
            "SELECT BIN_TO_UUID(id) AS id, 'ACT' AS type, name, code, status,
                    base_price_minor_units, base_price_currency, tax_category_code,
                    0 AS requires_prescription
            FROM catalog__acts
            WHERE tenant_id = :tenantId AND name LIKE :pattern
            LIMIT %d",
            $halfLimit,
        );

        $articleSql = \sprintf(
            "SELECT BIN_TO_UUID(id) AS id, 'ARTICLE' AS type, name, code, status,
                    base_price_minor_units, base_price_currency, tax_category_code,
                    COALESCE(drug_requires_rx, 0) AS requires_prescription
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
                id: RowAccessor::string($row, 'id'),
                type: RowAccessor::string($row, 'type'),
                name: RowAccessor::string($row, 'name'),
                code: RowAccessor::string($row, 'code'),
                status: RowAccessor::string($row, 'status'),
                basePriceMinorUnits: RowAccessor::int($row, 'base_price_minor_units'),
                basePriceCurrency: RowAccessor::string($row, 'base_price_currency'),
                taxCategoryCode: RowAccessor::string($row, 'tax_category_code'),
                requiresPrescription: 1 === RowAccessor::int($row, 'requires_prescription'),
            );
        }

        return \array_slice($results, 0, $limit);
    }
}
