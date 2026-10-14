<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Application\Query\WatchStock;

use App\Context\Inventory\Application\Port\StockOverviewReadRepositoryInterface;
use App\Context\Inventory\Application\Query\StockOverview\ClinicStock;
use App\Context\Inventory\Application\Query\StockOverview\GetClinicStock;
use App\Context\Inventory\Application\Query\StockOverview\GetClinicStockHandler;
use App\Context\Inventory\Application\Query\StockOverview\LotRow;
use App\Context\Inventory\Application\Query\StockOverview\OutflowRow;
use App\Context\Inventory\Application\Query\StockOverview\StockSnapshotRow;
use App\Context\Inventory\Application\Query\WatchStock\StockWatchRow;
use App\Context\Inventory\Application\Query\WatchStock\WatchStock;
use App\Context\Inventory\Application\Query\WatchStock\WatchStockHandler;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertSeverity;
use App\Tests\Shared\Time\FrozenClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WatchStockHandler::class)]
#[CoversClass(WatchStock::class)]
#[CoversClass(StockWatchRow::class)]
#[CoversClass(GetClinicStockHandler::class)]
#[CoversClass(GetClinicStock::class)]
#[CoversClass(ClinicStock::class)]
#[CoversClass(OutflowRow::class)]
final class WatchStockHandlerTest extends TestCase
{
    private const string CLINIC = '01970004-0000-7000-8000-000000000001';

    public function testEachKindOfAttentionIsFiledUnderItsSeverity(): void
    {
        $rows = $this->watch(
            [
                $this->item('expired', threshold: '5'),
                $this->item('out', threshold: '5'),
                $this->item('low', threshold: '10'),
                $this->item('fine', threshold: '10'),
                $this->item('never', threshold: '6'),
                $this->item('untracked', trackStock: false),
                $this->item('archived', status: 'ARCHIVED'),
            ],
            [
                'si-expired' => [
                    $this->lot('expired', 'L-PAST', '4', '2026-08-15', 'EXPIRED'),
                    $this->lot('expired', 'L-ACTIVE-PAST', '2', '2026-08-20'),
                    $this->lot('expired', 'L-OK', '30', '2027-08-01'),
                ],
                'si-out'  => [$this->lot('out', 'L-EMPTY', '0', '2027-01-01', 'DEPLETED')],
                'si-low'  => [$this->lot('low', 'L-LOW', '6', '2026-09-30'), $this->lot('low', 'L-NEW', '4', '2027-12-31')],
                'si-fine' => [
                    $this->lot('fine', 'L-FINE', '50', '2027-12-31'),
                    $this->lot('fine', 'L-RECALL', '0', '2027-12-31', 'RECALLED', recallReason: 'Rappel labo', changedAt: '2026-08-12 10:00:00'),
                    $this->lot('fine', 'L-OLD-RECALL', '0', '2027-12-31', 'RECALLED', changedAt: '2026-05-01 10:00:00'),
                    $this->lot('fine', 'L-UNDATED', '0', '2027-12-31', 'RECALLED'),
                ],
            ],
        );

        $seen = array_map(static fn (StockWatchRow $row): string => $row->kind->value . ':' . ($row->lotNumber ?? $row->articleId) . ':' . $row->severity->value, $rows);

        self::assertSame([
            'EXPIRED_LOT:L-PAST:CRITICAL',
            'EXPIRED_LOT:L-ACTIVE-PAST:CRITICAL',
            'OUT_OF_STOCK:a-out:CRITICAL',
            'BELOW_THRESHOLD:a-low:WARNING',
            'EXPIRING_LOT:L-LOW:WARNING',
            'RECALLED_LOT:L-RECALL:INFO',
        ], $seen);

        $expiring = $rows[4];
        self::assertSame(40, $expiring->days);
        self::assertSame('6', $expiring->quantity);
        self::assertSame('10', $expiring->threshold);
        self::assertSame('2026-09-30', $expiring->expiryDate);
        self::assertSame(-6, $rows[0]->days);
        self::assertSame('Rappel labo', $rows[5]->recallReason);
        self::assertSame('2026-08-12 10:00:00', $rows[5]->recalledAt);
        self::assertSame('0', $rows[2]->quantity);
        self::assertSame(StockAlertSeverity::WARNING, $rows[3]->severity);
        self::assertSame('10.000000', $rows[3]->quantity);
    }

    public function testAClinicThatFollowsNothingHasNothingToWatch(): void
    {
        self::assertSame([], $this->watch([], []));
    }

    public function testTheClinicStockIsReadInOneGo(): void
    {
        $item = $this->item('a');
        $lot  = $this->lot('a', 'L-1', '3', '2027-01-01');

        $repo = $this->createMock(StockOverviewReadRepositoryInterface::class);
        $repo->method('snapshotsOfClinic')->willReturn(['a-a' => $item]);
        $repo->expects(self::once())->method('lotsFor')->with(self::anything(), ['si-a'])->willReturn(['si-a' => [$lot]]);
        $repo->expects(self::once())->method('outflows')
            ->with(self::anything(), self::callback(static fn (\DateTimeImmutable $today): bool => 'Europe/Paris' === $today->getTimezone()->getName()), 8)
            ->willReturn(['a-a' => new OutflowRow('a-a', ['1.000000'], '1.000000')])
        ;
        $repo->expects(self::once())->method('latestMovements')->with(self::anything(), 3)->willReturn([]);
        $repo->method('lastCountAt')->willReturn(new \DateTimeImmutable('2026-08-01'));

        $stock = new GetClinicStockHandler($repo, new FrozenClock(new \DateTimeImmutable('2026-08-21 10:00:00')))(new GetClinicStock(self::CLINIC, 8, 3));

        self::assertSame(['a-a' => $item], $stock->items);
        self::assertSame(['si-a' => [$lot]], $stock->lots);
        self::assertSame('1.000000', $stock->outflows['a-a']->usedLastThirtyDays);
        self::assertSame('2026-08-01', $stock->lastCountAt?->format('Y-m-d'));
    }

    public function testAClinicWithoutItemsReadsNoLots(): void
    {
        $repo = $this->createMock(StockOverviewReadRepositoryInterface::class);
        $repo->method('snapshotsOfClinic')->willReturn([]);
        $repo->expects(self::never())->method('lotsFor');
        $repo->method('outflows')->willReturn([]);
        $repo->method('latestMovements')->willReturn([]);
        $repo->method('lastCountAt')->willReturn(null);

        $stock = new GetClinicStockHandler($repo, new FrozenClock(new \DateTimeImmutable('2026-08-21')))(new GetClinicStock(self::CLINIC));

        self::assertSame([], $stock->lots);
        self::assertNull($stock->lastCountAt);
    }

    /**
     * @param list<StockSnapshotRow>      $items
     * @param array<string, list<LotRow>> $lots
     *
     * @return list<StockWatchRow>
     */
    private function watch(array $items, array $lots): array
    {
        $repo = $this->createStub(StockOverviewReadRepositoryInterface::class);
        $repo->method('snapshotsOfClinic')->willReturn(array_combine(array_map(static fn (StockSnapshotRow $i): string => $i->articleId, $items), $items));
        $repo->method('lotsFor')->willReturn($lots);

        // 23:30 in Paris is already the next day in UTC terms of the clock: the clinic's calendar wins.
        return new WatchStockHandler($repo, new FrozenClock(new \DateTimeImmutable('2026-08-20 22:30:00', new \DateTimeZone('UTC'))))(new WatchStock(self::CLINIC, 'Europe/Paris'));
    }

    private function item(string $name, string $threshold = '0', bool $trackStock = true, string $status = 'ACTIVE'): StockSnapshotRow
    {
        return new StockSnapshotRow(
            stockItemId: 'si-' . $name,
            articleId: 'a-' . $name,
            onHand: '0',
            reserved: '0',
            available: '0',
            threshold: $threshold,
            unit: 'TABLET',
            thresholdType: 'ABSOLUTE',
            trackStock: $trackStock,
            status: $status,
            activeLotCount: 0,
            earliestExpiry: null,
            expiredLotCount: 0,
            recalledLotCount: 0,
        );
    }

    private function lot(string $item, string $number, string $quantity, string $expiry, string $status = 'ACTIVE', ?string $recallReason = null, ?string $changedAt = null): LotRow
    {
        return new LotRow(
            id: 'lot-' . $number,
            stockItemId: 'si-' . $item,
            number: $number,
            quantity: $quantity,
            unit: 'TABLET',
            expiryDate: $expiry,
            status: $status,
            recallReason: $recallReason,
            changedAt: $changedAt,
        );
    }
}
