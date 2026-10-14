<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\WatchStock;

use App\Context\Inventory\Application\Port\StockOverviewReadRepositoryInterface;
use App\Context\Inventory\Application\Query\StockOverview\LotRow;
use App\Context\Inventory\Application\Query\StockOverview\StockSnapshotRow;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertSeverity;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Reads the stock as it stands rather than the alert projection: a lot
 * expires with the calendar, not with an event, and the page and the
 * navigation badge must agree on what they count.
 *
 * Critical: a lot past its date still on the shelf, an article received
 * before and now without usable stock. To watch: an article at or under its
 * threshold, a usable lot expiring within 60 days. For information: a lot
 * recalled during the last 30 days. An article never received is not an
 * alert — the clinic has not stocked it yet.
 */
#[AsMessageHandler]
final readonly class WatchStockHandler
{
    public const int EXPIRING_WITHIN_DAYS = 60;

    public const int RECALL_KEPT_DAYS = 30;

    public function __construct(
        private StockOverviewReadRepositoryInterface $stock,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<StockWatchRow>
     */
    public function __invoke(WatchStock $query): array
    {
        $clinicId = ClinicId::fromString($query->clinicId);
        $items    = array_values(array_filter(
            $this->stock->snapshotsOfClinic($clinicId),
            static fn (StockSnapshotRow $item): bool => $item->trackStock && !$item->isArchived(),
        ));

        if ([] === $items) {
            return [];
        }

        $lots  = $this->stock->lotsFor($clinicId, array_map(static fn (StockSnapshotRow $item): string => $item->stockItemId, $items));
        $today = $this->clock->now()->setTimezone(new \DateTimeZone($query->timeZone))->setTime(0, 0);
        $rows  = [];

        foreach ($items as $item) {
            array_push($rows, ...$this->watch($item, $lots[$item->stockItemId] ?? [], $today));
        }

        return $rows;
    }

    /**
     * @param list<LotRow> $lots
     *
     * @return list<StockWatchRow>
     */
    private function watch(StockSnapshotRow $item, array $lots, \DateTimeImmutable $today): array
    {
        $rows   = [];
        $usable = '0';

        foreach ($lots as $lot) {
            $days = $this->daysUntil($lot->expiryDate, $today);

            if ($this->holdsSomething($lot) && ('EXPIRED' === $lot->status || ('ACTIVE' === $lot->status && $days < 0))) {
                $rows[] = $this->row(StockWatchKind::EXPIRED_LOT, StockAlertSeverity::CRITICAL, $item, $lot->quantity, $lot, $days);
            }

            if ('ACTIVE' === $lot->status && $days >= 0) {
                $usable = bcadd($usable, $this->numeric($lot->quantity), 6);
            }
        }

        if ([] === $lots) {
            return $rows;
        }

        if (0 >= bccomp($usable, '0', 6)) {
            $rows[] = $this->row(StockWatchKind::OUT_OF_STOCK, StockAlertSeverity::CRITICAL, $item, $usable);
        } elseif (0 < bccomp($this->numeric($item->threshold), '0', 6) && 0 >= bccomp($usable, $this->numeric($item->threshold), 6)) {
            $rows[] = $this->row(StockWatchKind::BELOW_THRESHOLD, StockAlertSeverity::WARNING, $item, $usable);
        }

        foreach ($lots as $lot) {
            $days = $this->daysUntil($lot->expiryDate, $today);

            if ('ACTIVE' === $lot->status && $this->holdsSomething($lot) && $days >= 0 && $days <= self::EXPIRING_WITHIN_DAYS) {
                $rows[] = $this->row(StockWatchKind::EXPIRING_LOT, StockAlertSeverity::WARNING, $item, $lot->quantity, $lot, $days);
            }
        }

        foreach ($lots as $lot) {
            if ('RECALLED' === $lot->status && $this->recalledLately($lot, $today)) {
                $rows[] = $this->row(StockWatchKind::RECALLED_LOT, StockAlertSeverity::INFO, $item, $lot->quantity, $lot);
            }
        }

        return $rows;
    }

    private function row(
        StockWatchKind $kind,
        StockAlertSeverity $severity,
        StockSnapshotRow $item,
        string $quantity,
        ?LotRow $lot = null,
        ?int $days = null,
    ): StockWatchRow {
        return new StockWatchRow(
            kind: $kind,
            severity: $severity,
            stockItemId: $item->stockItemId,
            articleId: $item->articleId,
            quantity: $quantity,
            threshold: $item->threshold,
            unit: $item->unit,
            lotId: $lot?->id,
            lotNumber: $lot?->number,
            expiryDate: $lot?->expiryDate,
            days: $days,
            recallReason: $lot?->recallReason,
            recalledAt: 'RECALLED' === $lot?->status ? $lot->changedAt : null,
        );
    }

    private function holdsSomething(LotRow $lot): bool
    {
        return 0 < bccomp($this->numeric($lot->quantity), '0', 6);
    }

    private function recalledLately(LotRow $lot, \DateTimeImmutable $today): bool
    {
        if (null === $lot->changedAt) {
            return false;
        }

        return new \DateTimeImmutable($lot->changedAt) >= $today->modify(\sprintf('-%d days', self::RECALL_KEPT_DAYS));
    }

    private function daysUntil(string $date, \DateTimeImmutable $today): int
    {
        $expiry = new \DateTimeImmutable($date, $today->getTimezone())->setTime(0, 0);

        return (int) $today->diff($expiry)->format('%r%a');
    }

    /**
     * @return numeric-string
     */
    private function numeric(string $amount): string
    {
        return is_numeric($amount) ? $amount : '0';
    }
}
