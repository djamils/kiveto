<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Procurement\Application\Port\SupplierAccountReadRepositoryInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineSupplierAccountReadRepository implements SupplierAccountReadRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findByClinic(string $clinicId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM procurement__supplier_accounts WHERE clinic_id = :clinicId ORDER BY created_at DESC',
            ['clinicId' => Uuid::fromString($clinicId)->toBinary()],
        );

        return array_map(fn (array $row): array => $this->hydrateRow($row), $rows);
    }

    /** @return array<string, mixed>|null */
    public function findById(string $id): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM procurement__supplier_accounts WHERE id = :id',
            ['id' => Uuid::fromString($id)->toBinary()],
        );

        return false !== $row ? $this->hydrateRow($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function hydrateRow(array $row): array
    {
        return [
            'id'                  => RowAccessor::uuid($row, 'id'),
            'clinicId'            => RowAccessor::uuid($row, 'clinic_id'),
            'supplierId'          => RowAccessor::uuid($row, 'supplier_id'),
            'customerCode'        => RowAccessor::string($row, 'customer_code'),
            'status'              => RowAccessor::string($row, 'status'),
            'billingAddressJson'  => RowAccessor::nullableString($row, 'billing_address_json'),
            'deliveryAddressJson' => RowAccessor::nullableString($row, 'delivery_address_json'),
            'notes'               => RowAccessor::nullableString($row, 'notes'),
            'createdAt'           => RowAccessor::string($row, 'created_at'),
            'updatedAt'           => RowAccessor::string($row, 'updated_at'),
        ];
    }
}
