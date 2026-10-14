<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Inventory\Application\Port\StockOverviewReadRepositoryInterface;
use App\Context\Inventory\Application\Query\StockOverview\LotRow;
use App\Context\Inventory\Application\Query\StockOverview\MovementCriteria;
use App\Context\Inventory\Application\Query\StockOverview\MovementRow;
use App\Context\Inventory\Application\Query\StockOverview\OutflowRow;
use App\Context\Inventory\Application\Query\StockOverview\StockSnapshotRow;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
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

    private const string MOVEMENT_COLUMNS = 'SELECT BIN_TO_UUID(m.id) AS id, BIN_TO_UUID(m.article_id) AS article_id,
                        BIN_TO_UUID(m.lot_id) AS lot_id, l.lot_number,
                        m.type, m.reason, m.quantity_amount, m.quantity_unit, m.decrease,
                        m.occurred_at, m.reference, BIN_TO_UUID(m.performed_by) AS performed_by, m.note,
                        m.balance';

    public function __construct(private Connection $connection)
    {
    }

    public function snapshotFor(ClinicId $clinicId, array $articleIds): array
    {
        if ([] === $articleIds) {
            return [];
        }

        return $this->snapshots($clinicId, $articleIds);
    }

    public function snapshotsOfClinic(ClinicId $clinicId): array
    {
        return $this->snapshots($clinicId, null);
    }

    public function lotsFor(ClinicId $clinicId, array $stockItemIds): array
    {
        if ([] === $stockItemIds) {
            return [];
        }

        // The reference of a lot is the one of the receipt that created it; the
        // reason of a recall is the note of its return to the supplier.
        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(l.id) AS id, BIN_TO_UUID(l.stock_item_id) AS stock_item_id,
                    l.lot_number, l.quantity_amount, l.quantity_unit, l.expiry_date, l.status,
                    l.received_at, l.updated_at,
                    (SELECT m.reference FROM inventory__stock_movements m
                      WHERE m.lot_id = l.id AND m.type = 'IN'
                      ORDER BY m.occurred_at ASC, m.created_at ASC LIMIT 1) AS reference,
                    (SELECT m.note FROM inventory__stock_movements m
                      WHERE m.lot_id = l.id AND m.reason = 'RECALL_RETURN_TO_SUPPLIER'
                      ORDER BY m.occurred_at DESC LIMIT 1) AS recall_reason
             FROM inventory__lots l
             JOIN inventory__stock_items s ON s.id = l.stock_item_id
             WHERE s.clinic_id = :clinicId AND l.stock_item_id IN (:stockItemIds)
             ORDER BY l.expiry_date ASC, l.lot_number ASC",
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
                receivedAt: RowAccessor::nullableString($row, 'received_at'),
                reference: RowAccessor::nullableString($row, 'reference'),
                recallReason: RowAccessor::nullableString($row, 'recall_reason'),
                changedAt: RowAccessor::nullableString($row, 'updated_at'),
            );
        }

        return $lots;
    }

    public function movements(ClinicId $clinicId, MovementCriteria $criteria): array
    {
        if ($criteria->isImpossible()) {
            return ['items' => [], 'total' => 0];
        }

        $where  = ['1 = 1'];
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
            $params['articleIds'] = $this->binaries($criteria->articleIds);
            $types['articleIds']  = ArrayParameterType::BINARY;
        }

        if (null !== $criteria->lotId) {
            $where[]         = 'm.lot_id = :lotId';
            $params['lotId'] = Uuid::fromString($criteria->lotId)->toBinary();
        }

        if (null !== $criteria->term && '' !== $criteria->term) {
            $match = ['l.lot_number LIKE :term', 'm.reference LIKE :term', 'm.note LIKE :term'];

            if ([] !== $criteria->termArticleIds) {
                $match[]                  = 'm.article_id IN (:termArticleIds)';
                $params['termArticleIds'] = $this->binaries($criteria->termArticleIds);
                $types['termArticleIds']  = ArrayParameterType::BINARY;
            }

            $where[]        = '(' . implode(' OR ', $match) . ')';
            $params['term'] = '%' . addcslashes($criteria->term, '%_\\') . '%';
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
            \sprintf('SELECT COUNT(*) FROM %s m LEFT JOIN inventory__lots l ON l.id = m.lot_id WHERE %s', $this->trail(), $condition),
            $params,
            $types,
        );

        $total = is_numeric($totalRaw) ? (int) $totalRaw : 0;

        if (0 === $total) {
            return ['items' => [], 'total' => 0];
        }

        $rows = $this->connection->fetchAllAssociative(
            \sprintf(
                '%s FROM %s m
                 LEFT JOIN inventory__lots l ON l.id = m.lot_id
                 WHERE %s
                 ORDER BY m.occurred_at DESC, m.created_at DESC, m.id DESC
                 LIMIT %d OFFSET %d',
                self::MOVEMENT_COLUMNS,
                $this->trail(),
                $condition,
                // Both come from a whitelist in the controller, never from raw
                // input, and MySQL refuses a bound parameter here anyway.
                $criteria->perPage,
                $criteria->offset(),
            ),
            $params,
            $types,
        );

        return ['items' => array_map($this->movementRow(...), $rows), 'total' => $total];
    }

    public function latestMovements(ClinicId $clinicId, int $perArticle): array
    {
        $rows = $this->connection->fetchAllAssociative(
            \sprintf(
                '%s FROM (SELECT t.*, ROW_NUMBER() OVER (PARTITION BY t.article_id ORDER BY t.occurred_at DESC, t.created_at DESC, t.id DESC) AS rank_in_article
                          FROM %s t) m
                 LEFT JOIN inventory__lots l ON l.id = m.lot_id
                 WHERE m.rank_in_article <= %d
                 ORDER BY m.occurred_at DESC, m.created_at DESC, m.id DESC',
                self::MOVEMENT_COLUMNS,
                $this->trail(),
                max(1, $perArticle),
            ),
            ['clinicId' => Uuid::fromString($clinicId->toString())->toBinary()],
        );

        $latest = [];

        foreach ($rows as $row) {
            $movement = $this->movementRow($row);

            $latest[$movement->articleId][] = $movement;
        }

        return $latest;
    }

    public function outflows(ClinicId $clinicId, \DateTimeImmutable $today, int $weeks): array
    {
        $weeks = max(1, $weeks);
        $day   = $today->format('Y-m-d');

        $rows = $this->connection->fetchAllAssociative(
            \sprintf(
                "SELECT BIN_TO_UUID(m.article_id) AS article_id,
                        FLOOR(DATEDIFF(:today, DATE(m.occurred_at)) / 7) AS week_ago,
                        SUM(CAST(m.quantity_amount AS %1\$s)) AS quantity,
                        SUM(CASE WHEN m.reason IN ('CONSUMPTION_CONSULTATION', 'SALE_RETAIL')
                                  AND DATEDIFF(:today, DATE(m.occurred_at)) < 30
                                 THEN CAST(m.quantity_amount AS %1\$s) ELSE 0 END) AS used
                 FROM inventory__stock_movements m
                 WHERE m.clinic_id = :clinicId AND m.type = 'OUT'
                   AND DATE(m.occurred_at) <= :today
                   AND DATEDIFF(:today, DATE(m.occurred_at)) < :span
                 GROUP BY m.article_id, week_ago",
                self::DECIMAL,
            ),
            [
                'clinicId' => Uuid::fromString($clinicId->toString())->toBinary(),
                'today'    => $day,
                'span'     => max($weeks * 7, 30),
            ],
        );

        /** @var array<string, array{weekly: array<int, numeric-string>, used: numeric-string}> $byArticle */
        $byArticle = [];

        foreach ($rows as $row) {
            $articleId = RowAccessor::string($row, 'article_id');
            $weekAgo   = (int) RowAccessor::string($row, 'week_ago');

            $byArticle[$articleId] ??= ['weekly' => array_fill(0, $weeks, '0.000000'), 'used' => '0.000000'];

            /** @var numeric-string $quantity */
            $quantity = RowAccessor::string($row, 'quantity');
            /** @var numeric-string $used */
            $used = RowAccessor::string($row, 'used');

            if ($weekAgo < $weeks) {
                $index                                   = $weeks - 1 - $weekAgo;
                $byArticle[$articleId]['weekly'][$index] = bcadd($byArticle[$articleId]['weekly'][$index], $quantity, 6);
            }

            $byArticle[$articleId]['used'] = bcadd($byArticle[$articleId]['used'], $used, 6);
        }

        $outflows = [];

        foreach ($byArticle as $articleId => $figures) {
            $outflows[$articleId] = new OutflowRow(
                articleId: $articleId,
                weekly: array_values($figures['weekly']),
                usedLastThirtyDays: $figures['used'],
            );
        }

        return $outflows;
    }

    public function lastCountAt(ClinicId $clinicId): ?\DateTimeImmutable
    {
        $last = $this->connection->fetchOne(
            "SELECT MAX(m.occurred_at) FROM inventory__stock_movements m
             WHERE m.clinic_id = :clinicId AND m.reason = 'PHYSICAL_INVENTORY'",
            ['clinicId' => Uuid::fromString($clinicId->toString())->toBinary()],
        );

        return \is_string($last) ? new \DateTimeImmutable($last) : null;
    }

    /**
     * @param list<string>|null $articleIds null for every article of the clinic
     *
     * @return array<string, StockSnapshotRow>
     */
    private function snapshots(ClinicId $clinicId, ?array $articleIds): array
    {
        $params = ['clinicId' => Uuid::fromString($clinicId->toString())->toBinary()];
        $types  = [];
        $filter = '';

        if (null !== $articleIds) {
            $filter               = ' AND s.article_id IN (:articleIds)';
            $params['articleIds'] = array_map(
                static fn (string $id): string => Uuid::fromString($id)->toBinary(),
                $articleIds,
            );
            $types['articleIds'] = ArrayParameterType::BINARY;
        }

        $rows = $this->connection->fetchAllAssociative(
            \sprintf(
                'SELECT BIN_TO_UUID(s.id) AS id, BIN_TO_UUID(s.article_id) AS article_id,
                        s.total_on_hand_amount, s.total_on_hand_unit, s.reserved_amount,
                        s.threshold_amount, s.threshold_unit, s.threshold_type,
                        s.track_stock, s.status, s.storage_location,
                        %s AS active_lots,
                        %s AS expired_lots,
                        %s AS recalled_lots,
                        (SELECT MIN(l.expiry_date) FROM inventory__lots l
                          WHERE l.stock_item_id = s.id AND l.status = \'ACTIVE\') AS earliest_expiry
                 FROM inventory__stock_items s
                 WHERE s.clinic_id = :clinicId%s
                 ORDER BY s.created_at ASC, s.id ASC',
                $this->lotCount('ACTIVE'),
                $this->lotCount('EXPIRED'),
                $this->lotCount('RECALLED'),
                $filter,
            ),
            $params,
            $types,
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
                location: RowAccessor::nullableString($row, 'storage_location'),
            );
        }

        return $snapshots;
    }

    /**
     * The clinic's trail with, on every movement, what its article had in
     * stock once it was done — computed over the whole trail of the article,
     * before any filter narrows it.
     */
    private function trail(): string
    {
        return \sprintf(
            "(SELECT mv.*, SUM(CASE
                        WHEN mv.type = 'IN' THEN CAST(mv.quantity_amount AS %1\$s)
                        WHEN mv.type = 'OUT' OR mv.decrease = 1 THEN -CAST(mv.quantity_amount AS %1\$s)
                        ELSE CAST(mv.quantity_amount AS %1\$s) END)
                    OVER (PARTITION BY mv.article_id ORDER BY mv.occurred_at, mv.created_at, mv.id ROWS UNBOUNDED PRECEDING) AS balance
              FROM inventory__stock_movements mv
              WHERE mv.clinic_id = :clinicId)",
            self::DECIMAL,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function movementRow(array $row): MovementRow
    {
        $quantity = RowAccessor::string($row, 'quantity_amount');

        return new MovementRow(
            id: RowAccessor::string($row, 'id'),
            articleId: RowAccessor::string($row, 'article_id'),
            lotId: RowAccessor::nullableString($row, 'lot_id'),
            lotNumber: RowAccessor::nullableString($row, 'lot_number'),
            type: RowAccessor::string($row, 'type'),
            reason: RowAccessor::string($row, 'reason'),
            quantity: 1 === RowAccessor::int($row, 'decrease') ? '-' . $quantity : $quantity,
            unit: RowAccessor::string($row, 'quantity_unit'),
            occurredAt: new \DateTimeImmutable(RowAccessor::string($row, 'occurred_at')),
            reference: RowAccessor::nullableString($row, 'reference'),
            performedBy: RowAccessor::nullableString($row, 'performed_by'),
            note: RowAccessor::nullableString($row, 'note'),
            balance: RowAccessor::nullableString($row, 'balance'),
        );
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function binaries(array $ids): array
    {
        return array_map(static fn (string $id): string => Uuid::fromString($id)->toBinary(), $ids);
    }

    private function lotCount(string $status): string
    {
        return \sprintf(
            "(SELECT COUNT(*) FROM inventory__lots l WHERE l.stock_item_id = s.id AND l.status = '%s')",
            $status,
        );
    }
}
