<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Inventory\Application\Port\StockItemReadRepositoryInterface;
use App\Context\Inventory\Application\Query\GetExpiringLots\ExpiringLotView;
use App\Context\Inventory\Application\Query\GetStockItem\LotView;
use App\Context\Inventory\Application\Query\GetStockItem\StockItemView;
use App\Context\Inventory\Application\Query\ListStockItemsByClinic\StockItemSummaryView;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemStatus;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineStockItemReadRepository implements StockItemReadRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function findById(StockItemId $id, ClinicId $clinicId): ?StockItemView
    {
        $row = $this->connection->fetchAssociative(
            'SELECT s.*, s.total_on_hand_amount, s.reserved_amount,
                    (CAST(s.total_on_hand_amount AS DECIMAL(20,6)) - CAST(s.reserved_amount AS DECIMAL(20,6))) AS available_amount
             FROM inventory__stock_items s
             WHERE s.id = :id AND s.clinic_id = :clinicId',
            [
                'id'       => Uuid::fromString($id->toString())->toBinary(),
                'clinicId' => Uuid::fromString($clinicId->toString())->toBinary(),
            ]
        );

        if (false === $row) {
            return null;
        }

        $lotRows = $this->connection->fetchAllAssociative(
            'SELECT l.* FROM inventory__lots l WHERE l.stock_item_id = :stockItemId ORDER BY l.expiry_date ASC',
            ['stockItemId' => Uuid::fromString($id->toString())->toBinary()]
        );

        $lots = array_map(
            static fn (array $lot): LotView => new LotView(
                id: Uuid::fromBinary(RowAccessor::string($lot, 'id'))->toString(),
                number: RowAccessor::string($lot, 'lot_number'),
                quantityAmount: RowAccessor::string($lot, 'quantity_amount'),
                quantityUnit: RowAccessor::string($lot, 'quantity_unit'),
                expiryDate: RowAccessor::string($lot, 'expiry_date'),
                status: RowAccessor::string($lot, 'status'),
            ),
            $lotRows
        );

        return new StockItemView(
            id: Uuid::fromBinary(RowAccessor::string($row, 'id'))->toString(),
            clinicId: Uuid::fromBinary(RowAccessor::string($row, 'clinic_id'))->toString(),
            articleId: Uuid::fromBinary(RowAccessor::string($row, 'article_id'))->toString(),
            totalOnHandAmount: RowAccessor::string($row, 'total_on_hand_amount'),
            totalOnHandUnit: RowAccessor::string($row, 'total_on_hand_unit'),
            availableQuantityAmount: RowAccessor::string($row, 'available_amount'),
            thresholdAmount: RowAccessor::string($row, 'threshold_amount'),
            thresholdUnit: RowAccessor::string($row, 'threshold_unit'),
            thresholdType: RowAccessor::string($row, 'threshold_type'),
            trackStock: (bool) $row['track_stock'],
            status: RowAccessor::string($row, 'status'),
            lots: $lots,
        );
    }

    /**
     * Returns pairs of [stockItemId, clinicId] for stock items that have at least one ACTIVE lot past its expiry date.
     *
     * @return list<array{stockItemId: string, clinicId: string}>
     */
    public function findItemsWithExpiredLots(?ClinicId $clinicId): array
    {
        $sql    = 'SELECT DISTINCT l.stock_item_id, s.clinic_id FROM inventory__lots l JOIN inventory__stock_items s ON s.id = l.stock_item_id WHERE l.status = :status AND l.expiry_date < CURDATE()';
        $params = ['status' => 'ACTIVE'];

        if (null !== $clinicId) {
            $sql .= ' AND s.clinic_id = :clinicId';
            $params['clinicId'] = Uuid::fromString($clinicId->toString())->toBinary();
        }

        $rows = $this->connection->fetchAllAssociative($sql, $params);

        return array_map(
            static fn (array $row): array => [
                'stockItemId' => Uuid::fromBinary(RowAccessor::string($row, 'stock_item_id'))->toString(),
                'clinicId'    => Uuid::fromBinary(RowAccessor::string($row, 'clinic_id'))->toString(),
            ],
            $rows
        );
    }

    /**
     * @return list<ExpiringLotView>
     */
    public function findLotsExpiringWithin(ClinicId $clinicId, int $days): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT l.id, l.stock_item_id, l.lot_number, l.quantity_amount, l.quantity_unit, l.expiry_date,
                    s.clinic_id, s.article_id,
                    DATEDIFF(l.expiry_date, CURDATE()) AS days_until_expiry
             FROM inventory__lots l
             JOIN inventory__stock_items s ON s.id = l.stock_item_id
             WHERE s.clinic_id = :clinicId
               AND l.status = :status
               AND l.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
             ORDER BY l.expiry_date ASC',
            [
                'clinicId' => Uuid::fromString($clinicId->toString())->toBinary(),
                'status'   => 'ACTIVE',
                'days'     => $days,
            ]
        );

        return array_map(
            static fn (array $row): ExpiringLotView => new ExpiringLotView(
                lotId: Uuid::fromBinary(RowAccessor::string($row, 'id'))->toString(),
                stockItemId: Uuid::fromBinary(RowAccessor::string($row, 'stock_item_id'))->toString(),
                clinicId: Uuid::fromBinary(RowAccessor::string($row, 'clinic_id'))->toString(),
                articleId: Uuid::fromBinary(RowAccessor::string($row, 'article_id'))->toString(),
                lotNumber: RowAccessor::string($row, 'lot_number'),
                quantityAmount: RowAccessor::string($row, 'quantity_amount'),
                quantityUnit: RowAccessor::string($row, 'quantity_unit'),
                expiryDate: RowAccessor::string($row, 'expiry_date'),
                daysUntilExpiry: RowAccessor::int($row, 'days_until_expiry'),
            ),
            $rows
        );
    }

    /**
     * @return list<StockItemSummaryView>
     */
    public function findByClinicAndStatus(ClinicId $clinicId, ?StockItemStatus $status, int $limit, int $offset): array
    {
        $sql = 'SELECT s.*,
                    (CAST(s.total_on_hand_amount AS DECIMAL(20,6)) - CAST(s.reserved_amount AS DECIMAL(20,6))) AS available_amount,
                    COUNT(l.id) AS active_lot_count
                FROM inventory__stock_items s
                LEFT JOIN inventory__lots l ON l.stock_item_id = s.id AND l.status = \'ACTIVE\'
                WHERE s.clinic_id = :clinicId';

        $params = ['clinicId' => Uuid::fromString($clinicId->toString())->toBinary()];

        if (null !== $status) {
            $sql .= ' AND s.status = :status';
            $params['status'] = $status->value;
        }

        $sql .= ' GROUP BY s.id ORDER BY s.id ASC LIMIT :limit OFFSET :offset';

        $rows = $this->connection->fetchAllAssociative($sql, array_merge($params, ['limit' => $limit, 'offset' => $offset]));

        return array_map(
            static fn (array $row): StockItemSummaryView => new StockItemSummaryView(
                id: Uuid::fromBinary(RowAccessor::string($row, 'id'))->toString(),
                clinicId: Uuid::fromBinary(RowAccessor::string($row, 'clinic_id'))->toString(),
                articleId: Uuid::fromBinary(RowAccessor::string($row, 'article_id'))->toString(),
                totalOnHandAmount: RowAccessor::string($row, 'total_on_hand_amount'),
                totalOnHandUnit: RowAccessor::string($row, 'total_on_hand_unit'),
                availableQuantityAmount: RowAccessor::string($row, 'available_amount'),
                thresholdAmount: RowAccessor::string($row, 'threshold_amount'),
                thresholdUnit: RowAccessor::string($row, 'threshold_unit'),
                trackStock: (bool) $row['track_stock'],
                status: RowAccessor::string($row, 'status'),
                activeLotCount: RowAccessor::int($row, 'active_lot_count'),
            ),
            $rows
        );
    }
}
