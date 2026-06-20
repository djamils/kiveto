<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Procurement\Application\Port\SupplierPricingReadRepositoryInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineSupplierPricingReadRepository implements SupplierPricingReadRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findByClinic(string $clinicId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM procurement__supplier_pricing WHERE clinic_id = :clinicId ORDER BY negotiated_at DESC',
            ['clinicId' => Uuid::fromString($clinicId)->toBinary()],
        );

        return array_map(fn (array $row): array => $this->hydrateRow($row), $rows);
    }

    /** @return array<string, mixed>|null */
    public function findByClinicAndEntry(string $clinicId, string $entryId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM procurement__supplier_pricing WHERE clinic_id = :clinicId AND supplier_catalog_entry_id = :entryId',
            [
                'clinicId' => Uuid::fromString($clinicId)->toBinary(),
                'entryId'  => Uuid::fromString($entryId)->toBinary(),
            ],
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
            'id'                 => RowAccessor::uuid($row, 'id'),
            'clinicId'           => RowAccessor::uuid($row, 'clinic_id'),
            'supplierId'         => RowAccessor::uuid($row, 'supplier_id'),
            'catalogEntryId'     => RowAccessor::uuid($row, 'supplier_catalog_entry_id'),
            'amountMinor'        => RowAccessor::int($row, 'amount_minor'),
            'amountCurrency'     => RowAccessor::string($row, 'amount_currency'),
            'discountPercentage' => RowAccessor::nullableString($row, 'discount_percentage'),
            'pricingNotes'       => RowAccessor::nullableString($row, 'pricing_notes'),
            'expiresAt'          => RowAccessor::nullableString($row, 'expires_at'),
            'negotiatedAt'       => RowAccessor::string($row, 'negotiated_at'),
            'createdAt'          => RowAccessor::string($row, 'created_at'),
            'updatedAt'          => RowAccessor::string($row, 'updated_at'),
        ];
    }
}
