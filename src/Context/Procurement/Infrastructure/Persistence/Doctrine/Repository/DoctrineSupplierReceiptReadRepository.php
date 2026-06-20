<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Procurement\Application\Port\SupplierReceiptReadRepositoryInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineSupplierReceiptReadRepository implements SupplierReceiptReadRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findByClinic(string $clinicId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM procurement__supplier_receipts WHERE clinic_id = :clinicId ORDER BY created_at DESC',
            ['clinicId' => Uuid::fromString($clinicId)->toBinary()],
        );

        return array_map(fn (array $row): array => $this->hydrateRow($row), $rows);
    }

    /** @return array<string, mixed>|null */
    public function findById(string $id): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM procurement__supplier_receipts WHERE id = :id',
            ['id' => Uuid::fromString($id)->toBinary()],
        );

        return false !== $row ? $this->hydrateRow($row) : null;
    }

    /** @return list<array<string, mixed>> */
    public function findPending(string $clinicId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM procurement__supplier_receipts WHERE clinic_id = :clinicId AND status = :status ORDER BY created_at DESC',
            [
                'clinicId' => Uuid::fromString($clinicId)->toBinary(),
                'status'   => 'PENDING_REVIEW',
            ],
        );

        return array_map(fn (array $row): array => $this->hydrateRow($row), $rows);
    }

    /** @return list<array<string, mixed>> */
    public function findUnmatched(string $clinicId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM procurement__unmatched_deliveries WHERE clinic_id = :clinicId AND resolved_at IS NULL ORDER BY received_at DESC',
            ['clinicId' => Uuid::fromString($clinicId)->toBinary()],
        );

        return array_map(static fn (array $row): array => [
            'id'                    => RowAccessor::uuid($row, 'id'),
            'clinicId'              => RowAccessor::uuid($row, 'clinic_id'),
            'supplierId'            => RowAccessor::uuid($row, 'supplier_id'),
            'deliveryNoteReference' => RowAccessor::string($row, 'delivery_note_reference'),
            'rawPayloadJson'        => RowAccessor::string($row, 'raw_payload_json'),
            'receivedAt'            => RowAccessor::string($row, 'received_at'),
        ], $rows);
    }

    public function existsBySupplierAndDeliveryNote(string $supplierId, string $ref): bool
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id FROM procurement__supplier_receipts WHERE supplier_id = :supplierId AND delivery_note_reference = :ref LIMIT 1',
            [
                'supplierId' => Uuid::fromString($supplierId)->toBinary(),
                'ref'        => $ref,
            ],
        );

        return false !== $row;
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
            'clinicId'              => RowAccessor::uuid($row, 'clinic_id'),
            'supplierId'            => RowAccessor::uuid($row, 'supplier_id'),
            'purchaseOrderId'       => RowAccessor::uuid($row, 'purchase_order_id'),
            'deliveryNoteReference' => RowAccessor::string($row, 'delivery_note_reference'),
            'matchType'             => RowAccessor::string($row, 'match_type'),
            'status'                => RowAccessor::string($row, 'status'),
            'validatedAt'           => RowAccessor::nullableString($row, 'validated_at'),
            'receivedBy'            => RowAccessor::nullableUuid($row, 'received_by'),
            'comment'               => RowAccessor::nullableString($row, 'comment'),
            'createdAt'             => RowAccessor::string($row, 'created_at'),
            'updatedAt'             => RowAccessor::string($row, 'updated_at'),
        ];
    }
}
