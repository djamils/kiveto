<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Application\Query\StockOverview;

use App\Context\Inventory\Application\Query\StockOverview\StockSnapshotRow;
use PHPUnit\Framework\TestCase;

final class StockSnapshotRowTest extends TestCase
{
    private const string TODAY = '2026-08-22';

    public function testAvailableAtZeroIsOutOfStock(): void
    {
        self::assertTrue($this->row(available: '0.000000')->isOutOfStock());
    }

    public function testReservedStockCanPutAnArticleOutOfStock(): void
    {
        $row = $this->row(onHand: '4.000000', reserved: '4.000000', available: '0.000000');

        self::assertTrue($row->isOutOfStock());
        self::assertFalse($row->isLow(), 'out of stock is not also low — the row says the worse of the two');
    }

    public function testAnUntrackedItemIsNeverOutOfStockNorLow(): void
    {
        $row = $this->row(available: '0.000000', trackStock: false);

        self::assertFalse($row->isOutOfStock());
        self::assertFalse($row->isLow());
    }

    public function testAvailableUnderTheThresholdIsLow(): void
    {
        self::assertTrue($this->row(available: '3.000000', threshold: '5.000000')->isLow());
    }

    public function testAvailableExactlyAtTheThresholdIsLow(): void
    {
        self::assertTrue($this->row(available: '5.000000', threshold: '5.000000')->isLow());
    }

    public function testAvailableAboveTheThresholdIsNotLow(): void
    {
        self::assertFalse($this->row(available: '5.000001', threshold: '5.000000')->isLow());
    }

    public function testAnItemWithoutAThresholdIsNeverLow(): void
    {
        self::assertFalse($this->row(available: '1.000000', threshold: '0.000000')->isLow());
    }

    public function testExpiresWithinCountsTheDaysAhead(): void
    {
        $row = $this->row(earliestExpiry: '2026-09-10');

        self::assertTrue($row->expiresWithin(30, $this->today()));
        self::assertFalse($row->expiresWithin(7, $this->today()));
    }

    public function testExpiresWithinIncludesTheLastDayOfTheWindow(): void
    {
        $row = $this->row(earliestExpiry: '2026-09-21');

        self::assertTrue($row->expiresWithin(30, $this->today()));
    }

    public function testAnItemWithoutAnExpiryNeverExpires(): void
    {
        self::assertFalse($this->row(earliestExpiry: null)->expiresWithin(30, $this->today()));
    }

    public function testAnUnreadableExpiryIsNotTreatedAsExpiring(): void
    {
        self::assertFalse($this->row(earliestExpiry: 'not-a-date')->expiresWithin(30, $this->today()));
    }

    public function testLotCountsAnswerTheirQuestions(): void
    {
        self::assertFalse($this->row()->hasExpiredLots());
        self::assertFalse($this->row()->hasRecalledLots());
        self::assertTrue($this->row(expiredLotCount: 1)->hasExpiredLots());
        self::assertTrue($this->row(recalledLotCount: 2)->hasRecalledLots());
    }

    public function testArchivedIsReadFromTheStatus(): void
    {
        self::assertFalse($this->row()->isArchived());
        self::assertTrue($this->row(status: 'ARCHIVED')->isArchived());
    }

    private function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::TODAY);
    }

    private function row(
        string $onHand = '10.000000',
        string $reserved = '0.000000',
        string $available = '10.000000',
        string $threshold = '0.000000',
        bool $trackStock = true,
        string $status = 'ACTIVE',
        ?string $earliestExpiry = null,
        int $expiredLotCount = 0,
        int $recalledLotCount = 0,
    ): StockSnapshotRow {
        return new StockSnapshotRow(
            stockItemId: '01960000-0000-7000-8000-0000000000a1',
            articleId: '01960000-0000-7000-8000-0000000000b1',
            onHand: $onHand,
            reserved: $reserved,
            available: $available,
            threshold: $threshold,
            unit: 'UNIT',
            thresholdType: 'ABSOLUTE',
            trackStock: $trackStock,
            status: $status,
            activeLotCount: 1,
            earliestExpiry: $earliestExpiry,
            expiredLotCount: $expiredLotCount,
            recalledLotCount: $recalledLotCount,
        );
    }
}
