<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Port;

use App\Context\Inventory\Application\Query\StockOverview\LotRow;
use App\Context\Inventory\Application\Query\StockOverview\MovementCriteria;
use App\Context\Inventory\Application\Query\StockOverview\MovementRow;
use App\Context\Inventory\Application\Query\StockOverview\OutflowRow;
use App\Context\Inventory\Application\Query\StockOverview\StockSnapshotRow;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;

/**
 * What the stock screens read: the figures of the clinic's articles, their lots,
 * their outflows and the movement trail.
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

    /**
     * Every stock item of the clinic, archived and untracked ones included.
     *
     * @return array<string, StockSnapshotRow> keyed by article id
     */
    public function snapshotsOfClinic(ClinicId $clinicId): array;

    /**
     * What left the shelf of each article, week by week up to today.
     *
     * @return array<string, OutflowRow> keyed by article id; an article without
     *                                   any outgoing movement is absent
     */
    public function outflows(ClinicId $clinicId, \DateTimeImmutable $today, int $weeks): array;

    /**
     * The latest movements of each article, newest first.
     *
     * @return array<string, list<MovementRow>> keyed by article id
     */
    public function latestMovements(ClinicId $clinicId, int $perArticle): array;

    /**
     * When the clinic last counted its stock.
     */
    public function lastCountAt(ClinicId $clinicId): ?\DateTimeImmutable;
}
