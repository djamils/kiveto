<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Port;

use App\Context\Inventory\Application\Query\StockOverview\LotRow;
use App\Context\Inventory\Application\Query\StockOverview\MovementCriteria;
use App\Context\Inventory\Application\Query\StockOverview\MovementRow;
use App\Context\Inventory\Application\Query\StockOverview\StockSnapshotRow;
use App\Context\Inventory\Application\Query\StockOverview\StockStateFilter;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;

/**
 * What the stock screens read: the figures for a set of articles, the articles
 * in a given state, and the movement trail.
 *
 * The stock page is a page of catalogue articles enriched with these figures, so
 * neither context reads the other's tables: Catalog decides which articles are
 * on the page and in what order, Inventory says what is behind them.
 */
interface StockOverviewReadRepositoryInterface
{
    /**
     * @param list<string> $articleIds
     *
     * @return array<string, StockSnapshotRow> keyed by article id; an article with
     *                                         no stock item is absent
     */
    public function snapshotFor(ClinicId $clinicId, array $articleIds): array;

    /**
     * @return list<string> article ids
     */
    public function articleIdsInState(
        ClinicId $clinicId,
        StockStateFilter $state,
        int $expiringWithinDays,
    ): array;

    /**
     * The lots of several stock items, newest expiry last so a page reads them
     * in the order they will be consumed.
     *
     * @param list<string> $stockItemIds
     *
     * @return array<string, list<LotRow>> keyed by stock item id
     */
    public function lotsFor(ClinicId $clinicId, array $stockItemIds): array;

    /**
     * @return array{items: list<MovementRow>, total: int}
     */
    public function movements(ClinicId $clinicId, MovementCriteria $criteria): array;
}
