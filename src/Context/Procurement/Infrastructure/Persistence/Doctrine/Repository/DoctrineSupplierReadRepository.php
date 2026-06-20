<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Procurement\Application\Port\SupplierReadRepositoryInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineSupplierReadRepository implements SupplierReadRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findAll(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM procurement__suppliers ORDER BY name ASC'
        );

        return array_map(fn (array $row): array => $this->hydrateRow($row), $rows);
    }

    /** @return array<string, mixed>|null */
    public function findById(string $id): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM procurement__suppliers WHERE id = :id',
            ['id' => Uuid::fromString($id)->toBinary()],
        );

        return false !== $row ? $this->hydrateRow($row) : null;
    }

    /** @return list<array<string, mixed>> */
    public function search(string $term, int $limit = 20): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM procurement__suppliers WHERE name LIKE :term ORDER BY name ASC LIMIT :limit',
            ['term' => '%' . $term . '%', 'limit' => $limit],
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
            'id'                 => RowAccessor::uuid($row, 'id'),
            'name'               => RowAccessor::string($row, 'name'),
            'code'               => RowAccessor::string($row, 'code'),
            'type'               => RowAccessor::string($row, 'type'),
            'countryCode'        => RowAccessor::string($row, 'country_code'),
            'defaultCurrency'    => RowAccessor::string($row, 'default_currency'),
            'integrationMode'    => RowAccessor::string($row, 'integration_mode'),
            'adapterIdentifier'  => RowAccessor::nullableString($row, 'adapter_identifier'),
            'status'             => RowAccessor::string($row, 'status'),
            'contactEmail'       => RowAccessor::nullableString($row, 'contact_email'),
            'contactPhone'       => RowAccessor::nullableString($row, 'contact_phone'),
            'contactPerson'      => RowAccessor::nullableString($row, 'contact_person'),
            'addressStreet'      => RowAccessor::nullableString($row, 'address_street'),
            'addressLine2'       => RowAccessor::nullableString($row, 'address_line2'),
            'postalCode'         => RowAccessor::nullableString($row, 'postal_code'),
            'contactCity'        => RowAccessor::nullableString($row, 'contact_city'),
            'addressCountryCode' => RowAccessor::nullableString($row, 'address_country_code'),
            'createdAt'          => RowAccessor::string($row, 'created_at'),
            'updatedAt'          => RowAccessor::string($row, 'updated_at'),
        ];
    }
}
