<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Procurement\Application\Port\SupplierCatalogReadRepositoryInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineSupplierCatalogReadRepository implements SupplierCatalogReadRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return list<array<string, mixed>> */
    public function search(string $term, string $supplierId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM procurement__supplier_catalog_entries WHERE supplier_id = :supplierId AND name LIKE :term ORDER BY name ASC LIMIT 50',
            [
                'supplierId' => Uuid::fromString($supplierId)->toBinary(),
                'term'       => '%' . $term . '%',
            ],
        );

        return array_map(fn (array $row): array => $this->hydrateRow($row), $rows);
    }

    /** @return array<string, mixed>|null */
    public function findById(string $id): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM procurement__supplier_catalog_entries WHERE id = :id LIMIT 1',
            ['id' => Uuid::fromString($id)->toBinary()],
        );

        return false !== $row ? $this->hydrateRow($row) : null;
    }

    /** @return array<string, mixed>|null */
    public function findBySupplierAndCode(string $supplierId, string $code): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM procurement__supplier_catalog_entries WHERE supplier_id = :supplierId AND supplier_product_code = :code LIMIT 1',
            [
                'supplierId' => Uuid::fromString($supplierId)->toBinary(),
                'code'       => $code,
            ],
        );

        return false !== $row ? $this->hydrateRow($row) : null;
    }

    /** @return list<array<string, mixed>> */
    public function findBySupplier(string $supplierId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM procurement__supplier_catalog_entries WHERE supplier_id = :supplierId ORDER BY name ASC',
            ['supplierId' => Uuid::fromString($supplierId)->toBinary()],
        );

        return array_map(fn (array $row): array => $this->hydrateRow($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function hydrateRow(array $row): array
    {
        return [
            'id'                    => RowAccessor::uuid($row, 'id'),
            'supplierId'            => RowAccessor::uuid($row, 'supplier_id'),
            'productCode'           => RowAccessor::string($row, 'supplier_product_code'),
            'name'                  => RowAccessor::string($row, 'name'),
            'gtin'                  => RowAccessor::nullableString($row, 'gtin'),
            'catalogPriceMinor'     => RowAccessor::int($row, 'catalog_price_minor'),
            'catalogPriceCurrency'  => RowAccessor::string($row, 'catalog_price_currency'),
            'catalogPriceValidFrom' => RowAccessor::string($row, 'catalog_price_valid_from'),
            'catalogPriceValidTo'   => RowAccessor::nullableString($row, 'catalog_price_valid_to'),
            'packagingUnit'         => RowAccessor::nullableString($row, 'packaging_unit'),
            'packagingAmount'       => RowAccessor::nullableString($row, 'packaging_amount'),
            'status'                => RowAccessor::string($row, 'status'),
            'createdAt'             => RowAccessor::string($row, 'created_at'),
            'updatedAt'             => RowAccessor::string($row, 'updated_at'),
        ];
    }
}
