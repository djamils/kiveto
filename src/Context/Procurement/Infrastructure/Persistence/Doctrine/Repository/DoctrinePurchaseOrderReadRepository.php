<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Procurement\Application\Port\PurchaseOrderReadRepositoryInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePurchaseOrderReadRepository implements PurchaseOrderReadRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findByClinic(string $clinicId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM procurement__purchase_orders WHERE clinic_id = :clinicId ORDER BY created_at DESC',
            ['clinicId' => Uuid::fromString($clinicId)->toBinary()],
        );

        return array_map(fn (array $row): array => $this->hydrateRow($row), $rows);
    }

    /** @return array<string, mixed>|null */
    public function findById(string $id): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM procurement__purchase_orders WHERE id = :id',
            ['id' => Uuid::fromString($id)->toBinary()],
        );

        return false !== $row ? $this->hydrateRow($row) : null;
    }

    /** @return array<string, mixed>|null */
    public function findByExternalReference(string $externalRef): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM procurement__purchase_orders WHERE external_reference_value = :ref',
            ['ref' => $externalRef],
        );

        return false !== $row ? $this->hydrateRow($row) : null;
    }

    /** @return list<array<string, mixed>> */
    public function findStale(int $daysThreshold): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM procurement__purchase_orders WHERE status = :status AND submitted_at < DATE_SUB(NOW(), INTERVAL :days DAY) ORDER BY submitted_at ASC',
            ['status' => 'PARTIALLY_RECEIVED', 'days' => $daysThreshold],
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
            'id'                          => RowAccessor::uuid($row, 'id'),
            'clinicId'                    => RowAccessor::uuid($row, 'clinic_id'),
            'supplierId'                  => RowAccessor::uuid($row, 'supplier_id'),
            'supplierAccountId'           => RowAccessor::uuid($row, 'supplier_account_id'),
            'orderNumber'                 => RowAccessor::string($row, 'order_number'),
            'currency'                    => RowAccessor::string($row, 'currency'),
            'status'                      => RowAccessor::string($row, 'status'),
            'externalReferenceValue'      => RowAccessor::nullableString($row, 'external_reference_value'),
            'externalReferenceProvidedBy' => RowAccessor::nullableString($row, 'external_reference_provided_by'),
            'deliveryAddressJson'         => RowAccessor::nullableString($row, 'delivery_address_json'),
            'pdfFileId'                   => RowAccessor::nullableString($row, 'pdf_file_id'),
            'submittedAt'                 => RowAccessor::nullableString($row, 'submitted_at'),
            'confirmedAt'                 => RowAccessor::nullableString($row, 'confirmed_at'),
            'createdAt'                   => RowAccessor::string($row, 'created_at'),
            'updatedAt'                   => RowAccessor::string($row, 'updated_at'),
        ];
    }
}
