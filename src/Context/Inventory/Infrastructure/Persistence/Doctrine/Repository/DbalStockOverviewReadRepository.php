<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Inventory\Application\Port\StockOverviewReadRepositoryInterface;
use App\Context\Inventory\Application\Query\StockOverview\LotRow;
use App\Context\Inventory\Application\Query\StockOverview\MovementCriteria;
use App\Context\Inventory\Application\Query\StockOverview\MovementRow;
use App\Context\Inventory\Application\Query\StockOverview\StockSnapshotRow;
use App\Context\Inventory\Application\Query\StockOverview\StockStateFilter;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * The read side of the stock screens.
 *
 * Quantities are stored as decimal strings, so the comparisons that decide "out"
 * and "low" are done in SQL with CAST rather than in PHP: the page has to be
 * able to narrow on them.
 */
final readonly class DbalStockOverviewReadRepository implements StockOverviewReadRepositoryInterface
{
    /** Enough digits for the units a clinic counts in, and then some. */
    private const string DECIMAL = 'DECIMAL(20,6)';

    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {
    }

    public function snapshotFor(ClinicId $clinicId, array $articleIds): array
    {
        if ([] === $articleIds) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            \sprintf(
                'SELECT BIN_TO_UUID(s.id) AS id, BIN_TO_UUID(s.article_id) AS article_id,
                        s.total_on_hand_amount, s.total_on_hand_unit, s.reserved_amount,
                        s.threshold_amount, s.threshold_unit, s.threshold_type,
                        s.track_stock, s.status,
                        %s AS active_lots,
                        %s AS expired_lots,
                        %s AS recalled_lots,
                        (SELECT MIN(l.expiry_date) FROM inventory__lots l
                          WHERE l.stock_item_id = s.id AND l.status = \'ACTIVE\') AS earliest_expiry
                 FROM inventory__stock_items s
                 WHERE s.clinic_id = :clinicId AND s.article_id IN (:articleIds)',
                $this->lotCount('ACTIVE'),
                $this->lotCount('EXPIRED'),
                $this->lotCount('RECALLED'),
            ),
            [
                'clinicId'   => Uuid::fromString($clinicId->toString())->toBinary(),
                'articleIds' => array_map(
                    static fn (string $id): string => Uuid::fromString($id)->toBinary(),
                    $articleIds,
                ),
            ],
            ['articleIds' => ArrayParameterType::BINARY],
        );

        $snapshots = [];

        foreach ($rows as $row) {
            $articleId = RowAccessor::string($row, 'article_id');
            $unit      = RowAccessor::string($row, 'total_on_hand_unit');

            /** @var numeric-string $onHand */
            $onHand = RowAccessor::string($row, 'total_on_hand_amount');
            /** @var numeric-string $reserved */
            $reserved = RowAccessor::string($row, 'reserved_amount');

            $snapshots[$articleId] = new StockSnapshotRow(
                stockItemId: RowAccessor::string($row, 'id'),
                articleId: $articleId,
                onHand: $onHand,
                reserved: $reserved,
                available: bcsub($onHand, $reserved, 6),
                threshold: RowAccessor::string($row, 'threshold_amount'),
                unit: $unit,
                thresholdType: RowAccessor::string($row, 'threshold_type'),
                trackStock: (bool) RowAccessor::int($row, 'track_stock'),
                status: RowAccessor::string($row, 'status'),
                activeLotCount: (int) RowAccessor::string($row, 'active_lots'),
                earliestExpiry: RowAccessor::nullableString($row, 'earliest_expiry'),
                expiredLotCount: (int) RowAccessor::string($row, 'expired_lots'),
                recalledLotCount: (int) RowAccessor::string($row, 'recalled_lots'),
            );
        }

        return $snapshots;
    }

    public function articleIdsInState(
        ClinicId $clinicId,
        StockStateFilter $state,
        int $expiringWithinDays,
    ): array {
        $params = ['clinicId' => Uuid::fromString($clinicId->toString())->toBinary()];

        $where = match ($state) {
            // Out of stock and low only mean something on a tracked article.
            StockStateFilter::OUT_OF_STOCK => \sprintf(
                's.track_stock = 1 AND CAST(s.total_on_hand_amount AS %s) - CAST(s.reserved_amount AS %1$s) <= 0',
                self::DECIMAL,
            ),
            StockStateFilter::LOW => \sprintf(
                's.track_stock = 1
                 AND CAST(s.threshold_amount AS %s) > 0
                 AND CAST(s.total_on_hand_amount AS %1$s) - CAST(s.reserved_amount AS %1$s) > 0
                 AND CAST(s.total_on_hand_amount AS %1$s) - CAST(s.reserved_amount AS %1$s)
                     <= CAST(s.threshold_amount AS %1$s)',
                self::DECIMAL,
            ),
            StockStateFilter::EXPIRING => $this->lotStateCondition('ACTIVE', 'AND l.expiry_date <= :limit'),
            StockStateFilter::EXPIRED  => $this->lotStateCondition('EXPIRED'),
            StockStateFilter::RECALLED => $this->lotStateCondition('RECALLED'),
        };

        if (StockStateFilter::EXPIRING === $state) {
            $params['limit'] = $this->clock->now()
                ->modify(\sprintf('+%d days', max(0, $expiringWithinDays)))
                ->format('Y-m-d')
            ;
        }

        $rows = $this->connection->fetchAllAssociative(
            \sprintf(
                'SELECT BIN_TO_UUID(s.article_id) AS article_id
                 FROM inventory__stock_items s
                 WHERE s.clinic_id = :clinicId AND %s',
                $where,
            ),
            $params,
        );

        return array_map(
            static fn (array $row): string => RowAccessor::string($row, 'article_id'),
            $rows,
        );
    }

    public function lotsFor(ClinicId $clinicId, array $stockItemIds): array
    {
        if ([] === $stockItemIds) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(l.id) AS id, BIN_TO_UUID(l.stock_item_id) AS stock_item_id,
                    l.lot_number, l.quantity_amount, l.quantity_unit, l.expiry_date, l.status
             FROM inventory__lots l
             JOIN inventory__stock_items s ON s.id = l.stock_item_id
             WHERE s.clinic_id = :clinicId AND l.stock_item_id IN (:stockItemIds)
             ORDER BY l.expiry_date ASC, l.lot_number ASC',
            [
                'clinicId'     => Uuid::fromString($clinicId->toString())->toBinary(),
                'stockItemIds' => array_map(
                    static fn (string $id): string => Uuid::fromString($id)->toBinary(),
                    $stockItemIds,
                ),
            ],
            ['stockItemIds' => ArrayParameterType::BINARY],
        );

        $lots = [];

        foreach ($rows as $row) {
            $stockItemId = RowAccessor::string($row, 'stock_item_id');

            $lots[$stockItemId][] = new LotRow(
                id: RowAccessor::string($row, 'id'),
                stockItemId: $stockItemId,
                number: RowAccessor::string($row, 'lot_number'),
                quantity: RowAccessor::string($row, 'quantity_amount'),
                unit: RowAccessor::string($row, 'quantity_unit'),
                expiryDate: RowAccessor::string($row, 'expiry_date'),
                status: RowAccessor::string($row, 'status'),
            );
        }

        return $lots;
    }

    public function movements(ClinicId $clinicId, MovementCriteria $criteria): array
    {
        if ($criteria->isImpossible()) {
            return ['items' => [], 'total' => 0];
        }

        $where  = ['m.clinic_id = :clinicId'];
        $params = ['clinicId' => Uuid::fromString($clinicId->toString())->toBinary()];
        $types  = [];

        if ([] !== $criteria->types) {
            $where[]         = 'm.type IN (:types)';
            $params['types'] = $criteria->types;
            $types['types']  = ArrayParameterType::STRING;
        }

        if ([] !== $criteria->reasons) {
            $where[]           = 'm.reason IN (:reasons)';
            $params['reasons'] = $criteria->reasons;
            $types['reasons']  = ArrayParameterType::STRING;
        }

        if (null !== $criteria->articleIds) {
            $where[]              = 'm.article_id IN (:articleIds)';
            $params['articleIds'] = array_map(
                static fn (string $id): string => Uuid::fromString($id)->toBinary(),
                $criteria->articleIds,
            );
            $types['articleIds'] = ArrayParameterType::BINARY;
        }

        if (null !== $criteria->from) {
            $where[]        = 'm.occurred_at >= :from';
            $params['from'] = $criteria->from->format('Y-m-d H:i:s');
        }

        if (null !== $criteria->to) {
            $where[]      = 'm.occurred_at <= :to';
            $params['to'] = $criteria->to->format('Y-m-d H:i:s');
        }

        $condition = implode(' AND ', $where);

        $totalRaw = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM inventory__stock_movements m WHERE ' . $condition,
            $params,
            $types,
        );

        $total = is_numeric($totalRaw) ? (int) $totalRaw : 0;

        if (0 === $total) {
            return ['items' => [], 'total' => 0];
        }

        $rows = $this->connection->fetchAllAssociative(
            \sprintf(
                'SELECT BIN_TO_UUID(m.id) AS id, BIN_TO_UUID(m.article_id) AS article_id,
                        BIN_TO_UUID(m.lot_id) AS lot_id, l.lot_number,
                        m.type, m.reason, m.quantity_amount, m.quantity_unit,
                        m.occurred_at, m.reference, BIN_TO_UUID(m.performed_by) AS performed_by, m.note
                 FROM inventory__stock_movements m
                 LEFT JOIN inventory__lots l ON l.id = m.lot_id
                 WHERE %s
                 ORDER BY m.occurred_at DESC, m.created_at DESC
                 LIMIT %d OFFSET %d',
                $condition,
                // Both come from a whitelist in the controller, never from raw
                // input, and MySQL refuses a bound parameter here anyway.
                $criteria->perPage,
                $criteria->offset(),
            ),
            $params,
            $types,
        );

        $items = array_map(
            static fn (array $row): MovementRow => new MovementRow(
                id: RowAccessor::string($row, 'id'),
                articleId: RowAccessor::string($row, 'article_id'),
                lotId: RowAccessor::nullableString($row, 'lot_id'),
                lotNumber: RowAccessor::nullableString($row, 'lot_number'),
                type: RowAccessor::string($row, 'type'),
                reason: RowAccessor::string($row, 'reason'),
                quantity: RowAccessor::string($row, 'quantity_amount'),
                unit: RowAccessor::string($row, 'quantity_unit'),
                occurredAt: new \DateTimeImmutable(RowAccessor::string($row, 'occurred_at')),
                reference: RowAccessor::nullableString($row, 'reference'),
                performedBy: RowAccessor::nullableString($row, 'performed_by'),
                note: RowAccessor::nullableString($row, 'note'),
            ),
            $rows,
        );

        return ['items' => $items, 'total' => $total];
    }

    private function lotCount(string $status): string
    {
        return \sprintf(
            "(SELECT COUNT(*) FROM inventory__lots l WHERE l.stock_item_id = s.id AND l.status = '%s')",
            $status,
        );
    }

    private function lotStateCondition(string $status, string $extra = ''): string
    {
        return \sprintf(
            "EXISTS (SELECT 1 FROM inventory__lots l
                      WHERE l.stock_item_id = s.id AND l.status = '%s' %s)",
            $status,
            $extra,
        );
    }
}
