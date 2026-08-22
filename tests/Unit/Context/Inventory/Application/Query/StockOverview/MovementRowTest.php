<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Application\Query\StockOverview;

use App\Context\Inventory\Application\Query\StockOverview\LotRow;
use App\Context\Inventory\Application\Query\StockOverview\MovementCriteria;
use App\Context\Inventory\Application\Query\StockOverview\MovementPage;
use App\Context\Inventory\Application\Query\StockOverview\MovementRow;
use App\Context\Inventory\Application\Query\StockOverview\StockStateFilter;
use PHPUnit\Framework\TestCase;

final class MovementRowTest extends TestCase
{
    public function testAReceiptComesIn(): void
    {
        self::assertTrue($this->movement(type: 'IN')->isIncoming());
    }

    public function testAConsumptionGoesOutEvenThoughItsQuantityIsPositive(): void
    {
        self::assertFalse($this->movement(type: 'OUT', quantity: '6.000000')->isIncoming());
    }

    public function testAnAdjustmentFollowsTheSignOfItsQuantity(): void
    {
        self::assertTrue($this->movement(type: 'ADJUSTMENT', quantity: '2.000000')->isIncoming());
        self::assertFalse($this->movement(type: 'ADJUSTMENT', quantity: '-2.000000')->isIncoming());
    }

    public function testTheCriteriaTurnsAPageIntoAnOffset(): void
    {
        self::assertSame(0, (new MovementCriteria(page: 1, perPage: 25))->offset());
        self::assertSame(50, (new MovementCriteria(page: 3, perPage: 25))->offset());
        self::assertSame(0, (new MovementCriteria(page: 0, perPage: 25))->offset());
    }

    public function testCriteriaNarrowedToNoArticleIsImpossible(): void
    {
        self::assertFalse((new MovementCriteria())->isImpossible());
        self::assertFalse((new MovementCriteria(articleIds: ['a']))->isImpossible());
        self::assertTrue((new MovementCriteria(articleIds: []))->isImpossible());
    }

    public function testThePageCountsWhatItShows(): void
    {
        $page = new MovementPage(items: [], total: 52, page: 3, perPage: 25);

        self::assertSame(3, $page->pageCount());
        self::assertSame(51, $page->firstItem());
        self::assertSame(52, $page->lastItem());
    }

    public function testAnEmptyPageStartsAtZero(): void
    {
        $page = new MovementPage(items: [], total: 0, page: 1, perPage: 25);

        self::assertSame(1, $page->pageCount());
        self::assertSame(0, $page->firstItem());
        self::assertSame(0, $page->lastItem());
    }

    public function testALotKnowsWhereItStands(): void
    {
        $today = new \DateTimeImmutable('2026-08-22');
        $lot   = $this->lot(expiryDate: '2026-08-30');

        self::assertTrue($lot->isActive());
        self::assertTrue($lot->expiresWithin(30, $today));
        self::assertFalse($lot->expiresWithin(3, $today));
        self::assertFalse($lot->isExpired($today));

        self::assertTrue($this->lot(expiryDate: '2026-08-01')->isExpired($today));
        self::assertFalse($this->lot(status: 'RECALLED')->isActive());
    }

    public function testAnUnreadableLotDateIsNeverExpiring(): void
    {
        $today = new \DateTimeImmutable('2026-08-22');
        $lot   = $this->lot(expiryDate: 'not-a-date');

        self::assertFalse($lot->expiresWithin(30, $today));
        self::assertFalse($lot->isExpired($today));
    }

    public function testEveryStateFilterIsNamedInFrench(): void
    {
        foreach (StockStateFilter::cases() as $state) {
            self::assertNotSame('', $state->label());
        }

        self::assertSame('En rupture', StockStateFilter::OUT_OF_STOCK->label());
        self::assertSame('Sous le seuil', StockStateFilter::LOW->label());
        self::assertSame('Bientôt périmé', StockStateFilter::EXPIRING->label());
        self::assertSame('Périmé', StockStateFilter::EXPIRED->label());
        self::assertSame('Lot rappelé', StockStateFilter::RECALLED->label());
    }

    private function movement(string $type, string $quantity = '10.000000'): MovementRow
    {
        return new MovementRow(
            id: '01960000-0000-7000-8000-0000000000c1',
            articleId: '01960000-0000-7000-8000-0000000000b1',
            lotId: null,
            lotNumber: 'LOT-1',
            type: $type,
            reason: 'RECEIPT_OPENING',
            quantity: $quantity,
            unit: 'UNIT',
            occurredAt: new \DateTimeImmutable('2026-08-22 10:00:00'),
            reference: null,
            performedBy: null,
            note: null,
        );
    }

    private function lot(string $expiryDate = '2027-01-01', string $status = 'ACTIVE'): LotRow
    {
        return new LotRow(
            id: '01960000-0000-7000-8000-0000000000d1',
            stockItemId: '01960000-0000-7000-8000-0000000000a1',
            number: 'LOT-1',
            quantity: '10.000000',
            unit: 'UNIT',
            expiryDate: $expiryDate,
            status: $status,
        );
    }
}
