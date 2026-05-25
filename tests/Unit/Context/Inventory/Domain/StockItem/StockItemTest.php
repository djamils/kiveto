<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Domain\StockItem;

use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\Event\LotAdded;
use App\Context\Inventory\Domain\StockItem\Event\LotDepleted;
use App\Context\Inventory\Domain\StockItem\Event\LotExpired;
use App\Context\Inventory\Domain\StockItem\Event\LotRecalled;
use App\Context\Inventory\Domain\StockItem\Event\StockAdjusted;
use App\Context\Inventory\Domain\StockItem\Event\StockConsumed;
use App\Context\Inventory\Domain\StockItem\Event\StockItemArchived;
use App\Context\Inventory\Domain\StockItem\Event\StockItemOpened;
use App\Context\Inventory\Domain\StockItem\Event\StockReceived;
use App\Context\Inventory\Domain\StockItem\Event\StockReservationReleased;
use App\Context\Inventory\Domain\StockItem\Event\StockReserved;
use App\Context\Inventory\Domain\StockItem\Exception\ArchivedStockItemException;
use App\Context\Inventory\Domain\StockItem\Exception\InconsistentLotDataException;
use App\Context\Inventory\Domain\StockItem\Exception\InsufficientStockException;
use App\Context\Inventory\Domain\StockItem\Exception\LotAlreadyTerminatedException;
use App\Context\Inventory\Domain\StockItem\Exception\StockNotEmptyOnArchiveException;
use App\Context\Inventory\Domain\StockItem\Service\FefoSelector;
use App\Context\Inventory\Domain\StockItem\StockItem;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotId;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotNumber;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotStatus;
use App\Context\Inventory\Domain\StockItem\ValueObject\Quantity;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemStatus;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockThreshold;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockThresholdType;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use PHPUnit\Framework\TestCase;

final class StockItemTest extends TestCase
{
    private const string STOCK_ITEM_ID = '01970001-0000-7000-8000-000000000001';
    private const string CLINIC_ID     = '550e8400-e29b-41d4-a716-446655440000';
    private const string ARTICLE_ID    = '550e8400-e29b-41d4-a716-446655440001';
    private const string LOT_ID_1      = '01970001-0000-7000-8000-000000000010';
    private const string LOT_ID_2      = '01970001-0000-7000-8000-000000000011';

    private UnitOfMeasure $unit;
    private FefoSelector $fefo;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->unit = UnitOfMeasure::fromString('UNIT');
        $this->fefo = new FefoSelector();
        $this->now  = new \DateTimeImmutable('2026-01-15 10:00:00');
    }

    // -------------------------------------------------------------------------
    // open()
    // -------------------------------------------------------------------------

    public function testOpenRecordsStockItemOpenedAndInitializesZeroQuantity(): void
    {
        $stockItem = $this->openStockItem();
        $events    = $stockItem->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(StockItemOpened::class, $events[0]);

        $event = $events[0];
        self::assertInstanceOf(StockItemOpened::class, $event);
        self::assertSame(self::STOCK_ITEM_ID, $event->stockItemId);
        self::assertSame(self::CLINIC_ID, $event->clinicId);
        self::assertSame(self::ARTICLE_ID, $event->articleId);
        self::assertSame('UNIT', $event->unit);
        self::assertTrue($event->trackStock);

        self::assertTrue($stockItem->totalOnHand()->isZero());
        self::assertTrue($stockItem->reservedQuantity()->isZero());
        self::assertSame(StockItemStatus::ACTIVE, $stockItem->status());
        self::assertSame([], $stockItem->lots());
    }

    // -------------------------------------------------------------------------
    // receiveStock()
    // -------------------------------------------------------------------------

    public function testReceiveStockCreatesNewLotAndEmitsLotAddedAndStockReceived(): void
    {
        $stockItem = $this->openStockItem();
        $_         = $stockItem->pullDomainEvents();

        $lotId      = LotId::fromString(self::LOT_ID_1);
        $expiryDate = new \DateTimeImmutable('+6 months');

        $returnedId = $stockItem->receiveStock(
            LotNumber::fromString('LOT-001'),
            Quantity::of('10.000000', $this->unit),
            $expiryDate,
            null,
            $this->now,
            $this->now,
            $lotId,
        );

        $events = $stockItem->pullDomainEvents();

        self::assertCount(2, $events);
        self::assertInstanceOf(LotAdded::class, $events[0]);
        self::assertInstanceOf(StockReceived::class, $events[1]);

        $lotAdded = $events[0];
        self::assertInstanceOf(LotAdded::class, $lotAdded);
        self::assertSame(self::LOT_ID_1, $lotAdded->lotId);
        self::assertSame('LOT-001', $lotAdded->lotNumber);
        self::assertSame('10.000000', $lotAdded->quantityAmount);

        self::assertSame('10.000000', $stockItem->totalOnHand()->amount());
        self::assertCount(1, $stockItem->lots());
        self::assertSame(self::LOT_ID_1, $returnedId?->toString());
    }

    public function testReceiveStockIncrementsExistingLotWithSameExpiryAndNoLotAdded(): void
    {
        $stockItem  = $this->openStockItem();
        $lotId      = LotId::fromString(self::LOT_ID_1);
        $expiryDate = new \DateTimeImmutable('+6 months');

        $stockItem->receiveStock(
            LotNumber::fromString('LOT-001'),
            Quantity::of('10.000000', $this->unit),
            $expiryDate,
            null,
            $this->now,
            $this->now,
            $lotId,
        );
        $_ = $stockItem->pullDomainEvents();

        // Same lot number, same expiry — should increment
        $stockItem->receiveStock(
            LotNumber::fromString('LOT-001'),
            Quantity::of('5.000000', $this->unit),
            $expiryDate,
            null,
            $this->now,
            $this->now,
            LotId::fromString(self::LOT_ID_2), // new ID unused
        );

        $events = $stockItem->pullDomainEvents();

        // Only StockReceived, no LotAdded
        self::assertCount(1, $events);
        self::assertInstanceOf(StockReceived::class, $events[0]);

        self::assertSame('15.000000', $stockItem->totalOnHand()->amount());
        self::assertCount(1, $stockItem->lots());
        self::assertSame('15.000000', $stockItem->lots()[0]->quantity()->amount());
    }

    public function testReceiveStockThrowsInconsistentLotDataExceptionOnExpiryMismatch(): void
    {
        $this->expectException(InconsistentLotDataException::class);

        $stockItem = $this->openStockItem();
        $lotId     = LotId::fromString(self::LOT_ID_1);

        $stockItem->receiveStock(
            LotNumber::fromString('LOT-001'),
            Quantity::of('10.000000', $this->unit),
            new \DateTimeImmutable('+6 months'),
            null,
            $this->now,
            $this->now,
            $lotId,
        );
        $_ = $stockItem->pullDomainEvents();

        // Same lot number, DIFFERENT expiry
        $stockItem->receiveStock(
            LotNumber::fromString('LOT-001'),
            Quantity::of('5.000000', $this->unit),
            new \DateTimeImmutable('+12 months'),
            null,
            $this->now,
            $this->now,
            LotId::fromString(self::LOT_ID_2),
        );
    }

    public function testReceiveStockThrowsLotAlreadyTerminatedExceptionOnExpiredLot(): void
    {
        $this->expectException(LotAlreadyTerminatedException::class);

        $stockItem  = $this->openStockItem();
        $lotId      = LotId::fromString(self::LOT_ID_1);
        $expiryDate = new \DateTimeImmutable('yesterday');

        $stockItem->receiveStock(
            LotNumber::fromString('LOT-EXPIRED'),
            Quantity::of('10.000000', $this->unit),
            $expiryDate,
            null,
            $this->now,
            $this->now,
            $lotId,
        );
        $_ = $stockItem->pullDomainEvents();

        // Expire the lot
        $stockItem->expireOutdatedLots(new \DateTimeImmutable('today'), $this->now);
        $_ = $stockItem->pullDomainEvents();

        // Receiving on an expired lot must throw
        $stockItem->receiveStock(
            LotNumber::fromString('LOT-EXPIRED'),
            Quantity::of('5.000000', $this->unit),
            $expiryDate,
            null,
            $this->now,
            $this->now,
            LotId::fromString(self::LOT_ID_2),
        );
    }

    // -------------------------------------------------------------------------
    // consumeStock()
    // -------------------------------------------------------------------------

    public function testConsumeStockSingleLotEmitsSingleStockConsumed(): void
    {
        $stockItem = $this->openStockItemWithLot(self::LOT_ID_1, 'LOT-001', '10.000000', '+6 months');
        $_         = $stockItem->pullDomainEvents();

        $consumptions = $stockItem->consumeStock(
            Quantity::of('3.000000', $this->unit),
            $this->fefo,
            $this->now,
            $this->now,
        );

        $events = $stockItem->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(StockConsumed::class, $events[0]);
        self::assertCount(1, $consumptions);
        self::assertSame('3.000000', $consumptions[0]->quantity->amount());
        self::assertSame('7.000000', $stockItem->totalOnHand()->amount());
    }

    public function testConsumeStockTraversesMultipleLotsInFefoOrder(): void
    {
        // L1 expires sooner → consumed first
        $stockItem = $this->openStockItem();
        $_         = $stockItem->pullDomainEvents();

        $this->receiveInto($stockItem, self::LOT_ID_1, 'LOT-001', '10.000000', '+2 months');
        $this->receiveInto($stockItem, self::LOT_ID_2, 'LOT-002', '5.000000', '+8 months');
        $_ = $stockItem->pullDomainEvents();

        $consumptions = $stockItem->consumeStock(
            Quantity::of('12.000000', $this->unit),
            $this->fefo,
            $this->now,
            $this->now,
        );

        self::assertCount(2, $consumptions);
        self::assertSame(self::LOT_ID_1, $consumptions[0]->lotId->toString());
        self::assertSame('10.000000', $consumptions[0]->quantity->amount());
        self::assertSame(self::LOT_ID_2, $consumptions[1]->lotId->toString());
        self::assertSame('2.000000', $consumptions[1]->quantity->amount());

        self::assertSame('3.000000', $stockItem->totalOnHand()->amount());
    }

    public function testConsumeStockDepletedLotEmitsLotDepletedBeforeStockConsumed(): void
    {
        $stockItem = $this->openStockItemWithLot(self::LOT_ID_1, 'LOT-001', '5.000000', '+6 months');
        $_         = $stockItem->pullDomainEvents();

        $stockItem->consumeStock(
            Quantity::of('5.000000', $this->unit),
            $this->fefo,
            $this->now,
            $this->now,
        );

        $events = $stockItem->pullDomainEvents();

        // LotDepleted first, then StockConsumed
        self::assertCount(2, $events);
        self::assertInstanceOf(LotDepleted::class, $events[0]);
        self::assertInstanceOf(StockConsumed::class, $events[1]);

        $depleted = $events[0];
        self::assertInstanceOf(LotDepleted::class, $depleted);
        self::assertSame(self::LOT_ID_1, $depleted->lotId);

        // Lot is now DEPLETED
        self::assertSame(LotStatus::DEPLETED, $stockItem->lots()[0]->status());
        self::assertTrue($stockItem->totalOnHand()->isZero());
    }

    public function testConsumeStockThrowsInsufficientStockWhenAvailableBelowRequested(): void
    {
        $this->expectException(InsufficientStockException::class);

        $stockItem = $this->openStockItemWithLot(self::LOT_ID_1, 'LOT-001', '5.000000', '+6 months');
        $_         = $stockItem->pullDomainEvents();

        $stockItem->consumeStock(
            Quantity::of('10.000000', $this->unit),
            $this->fefo,
            $this->now,
            $this->now,
        );
    }

    public function testConsumeStockRespectsReservedQuantityInAvailabilityCheck(): void
    {
        $this->expectException(InsufficientStockException::class);

        $stockItem = $this->openStockItemWithLot(self::LOT_ID_1, 'LOT-001', '10.000000', '+6 months');
        $_         = $stockItem->pullDomainEvents();

        // Reserve 8 units → available = 2
        $stockItem->reserveStock(Quantity::of('8.000000', $this->unit), $this->now);
        $_ = $stockItem->pullDomainEvents();

        // Trying to consume 5 must fail (only 2 available)
        $stockItem->consumeStock(
            Quantity::of('5.000000', $this->unit),
            $this->fefo,
            $this->now,
            $this->now,
        );
    }

    public function testConsumeStockNoopWhenTrackStockFalse(): void
    {
        $stockItem = $this->openStockItem(trackStock: false);
        $_         = $stockItem->pullDomainEvents();

        $consumptions = $stockItem->consumeStock(
            Quantity::of('5.000000', $this->unit),
            $this->fefo,
            $this->now,
            $this->now,
        );

        $events = $stockItem->pullDomainEvents();

        self::assertSame([], $consumptions);
        self::assertSame([], $events);
        self::assertTrue($stockItem->totalOnHand()->isZero());
    }

    // -------------------------------------------------------------------------
    // reserveStock() / releaseReservation()
    // -------------------------------------------------------------------------

    public function testReserveStockDecreasesAvailableQuantity(): void
    {
        $stockItem = $this->openStockItemWithLot(self::LOT_ID_1, 'LOT-001', '10.000000', '+6 months');
        $_         = $stockItem->pullDomainEvents();

        $stockItem->reserveStock(Quantity::of('3.000000', $this->unit), $this->now);

        $events = $stockItem->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(StockReserved::class, $events[0]);
        self::assertSame('3.000000', $stockItem->reservedQuantity()->amount());
        self::assertSame('7.000000', $stockItem->availableQuantity()->amount());
        self::assertSame('10.000000', $stockItem->totalOnHand()->amount());
    }

    public function testReleaseReservationRestoresAvailableQuantity(): void
    {
        $stockItem = $this->openStockItemWithLot(self::LOT_ID_1, 'LOT-001', '10.000000', '+6 months');
        $_         = $stockItem->pullDomainEvents();

        $stockItem->reserveStock(Quantity::of('3.000000', $this->unit), $this->now);
        $_ = $stockItem->pullDomainEvents();

        $stockItem->releaseReservation(Quantity::of('3.000000', $this->unit), $this->now);

        $events = $stockItem->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(StockReservationReleased::class, $events[0]);
        self::assertSame('0.000000', $stockItem->reservedQuantity()->amount());
        self::assertSame('10.000000', $stockItem->availableQuantity()->amount());
    }

    // -------------------------------------------------------------------------
    // adjustStock()
    // -------------------------------------------------------------------------

    public function testAdjustStockPositiveDeltaUpdatesLotAndTotalOnHand(): void
    {
        $stockItem = $this->openStockItemWithLot(self::LOT_ID_1, 'LOT-001', '10.000000', '+6 months');
        $_         = $stockItem->pullDomainEvents();

        $stockItem->adjustStock(
            LotId::fromString(self::LOT_ID_1),
            Quantity::of('15.000000', $this->unit),
            'physical count correction',
            $this->now,
            $this->now,
        );

        $events = $stockItem->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(StockAdjusted::class, $events[0]);

        $event = $events[0];
        self::assertInstanceOf(StockAdjusted::class, $event);
        self::assertSame('5.000000', $event->deltaAmount);
        self::assertSame('15.000000', $stockItem->totalOnHand()->amount());
        self::assertSame('15.000000', $stockItem->lots()[0]->quantity()->amount());
    }

    public function testAdjustStockNegativeDeltaUpdatesLotAndTotalOnHand(): void
    {
        $stockItem = $this->openStockItemWithLot(self::LOT_ID_1, 'LOT-001', '10.000000', '+6 months');
        $_         = $stockItem->pullDomainEvents();

        $stockItem->adjustStock(
            LotId::fromString(self::LOT_ID_1),
            Quantity::of('6.000000', $this->unit),
            'physical count correction',
            $this->now,
            $this->now,
        );

        $events = $stockItem->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(StockAdjusted::class, $events[0]);

        $event = $events[0];
        self::assertInstanceOf(StockAdjusted::class, $event);
        self::assertSame('-4.000000', $event->deltaAmount);
        self::assertSame('6.000000', $stockItem->totalOnHand()->amount());
    }

    public function testAdjustStockToZeroDepletesLotAndEmitsLotDepleted(): void
    {
        $stockItem = $this->openStockItemWithLot(self::LOT_ID_1, 'LOT-001', '10.000000', '+6 months');
        $_         = $stockItem->pullDomainEvents();

        $stockItem->adjustStock(
            LotId::fromString(self::LOT_ID_1),
            Quantity::of('0.000000', $this->unit),
            'lost / discarded',
            $this->now,
            $this->now,
        );

        $events = $stockItem->pullDomainEvents();

        self::assertCount(2, $events);
        self::assertInstanceOf(LotDepleted::class, $events[0]);
        self::assertInstanceOf(StockAdjusted::class, $events[1]);
        self::assertSame(LotStatus::DEPLETED, $stockItem->lots()[0]->status());
        self::assertTrue($stockItem->totalOnHand()->isZero());
    }

    // -------------------------------------------------------------------------
    // markLotAsRecalled()
    // -------------------------------------------------------------------------

    public function testMarkLotAsRecalledZerosLotQuantityAndReducesTotalOnHand(): void
    {
        $stockItem = $this->openStockItemWithLot(self::LOT_ID_1, 'LOT-001', '10.000000', '+6 months');
        $_         = $stockItem->pullDomainEvents();

        $recalledQty = $stockItem->markLotAsRecalled(
            LotId::fromString(self::LOT_ID_1),
            'contamination',
            $this->now,
            $this->now,
        );

        $events = $stockItem->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(LotRecalled::class, $events[0]);

        $event = $events[0];
        self::assertInstanceOf(LotRecalled::class, $event);
        self::assertSame('10.000000', $recalledQty->amount());
        self::assertSame('10.000000', $event->recalledQuantityAmount);
        self::assertSame(LotStatus::RECALLED, $stockItem->lots()[0]->status());
        self::assertTrue($stockItem->lots()[0]->quantity()->isZero());
        self::assertTrue($stockItem->totalOnHand()->isZero());
    }

    // -------------------------------------------------------------------------
    // expireOutdatedLots()
    // -------------------------------------------------------------------------

    public function testExpireOutdatedLotsOnlyProcessesActiveLots(): void
    {
        $stockItem = $this->openStockItem();
        $_         = $stockItem->pullDomainEvents();

        // One expired lot, one still active
        $this->receiveInto($stockItem, self::LOT_ID_1, 'LOT-001', '5.000000', '-1 day');
        $this->receiveInto($stockItem, self::LOT_ID_2, 'LOT-002', '8.000000', '+6 months');
        $_ = $stockItem->pullDomainEvents();

        $stockItem->expireOutdatedLots(new \DateTimeImmutable('today'), $this->now);

        $events = $stockItem->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(LotExpired::class, $events[0]);

        $lotExpired = $events[0];
        self::assertInstanceOf(LotExpired::class, $lotExpired);
        self::assertSame(self::LOT_ID_1, $lotExpired->lotId);
        self::assertSame('5.000000', $lotExpired->quantityAtExpiry);

        // totalOnHand reduces by the expired lot quantity
        self::assertSame('8.000000', $stockItem->totalOnHand()->amount());
    }

    public function testExpireOutdatedLotsSecondCallIsNoOp(): void
    {
        $stockItem = $this->openStockItemWithLot(self::LOT_ID_1, 'LOT-001', '5.000000', '-1 day');
        $_         = $stockItem->pullDomainEvents();

        $stockItem->expireOutdatedLots(new \DateTimeImmutable('today'), $this->now);
        $_ = $stockItem->pullDomainEvents();

        // Second call on already EXPIRED lot → no events
        $stockItem->expireOutdatedLots(new \DateTimeImmutable('today'), $this->now);

        $events = $stockItem->pullDomainEvents();

        self::assertSame([], $events);
    }

    public function testExpireOutdatedLotsPreservesHistoricalQuantityOnExpiredLot(): void
    {
        $stockItem = $this->openStockItemWithLot(self::LOT_ID_1, 'LOT-001', '5.000000', '-1 day');
        $_         = $stockItem->pullDomainEvents();

        $stockItem->expireOutdatedLots(new \DateTimeImmutable('today'), $this->now);
        $_ = $stockItem->pullDomainEvents();

        $lot = $stockItem->lots()[0];

        // Lot quantity is NOT zeroed (preserved for pharmacovigilance)
        self::assertSame('5.000000', $lot->quantity()->amount());
        self::assertSame(LotStatus::EXPIRED, $lot->status());

        // But totalOnHand has been reduced
        self::assertTrue($stockItem->totalOnHand()->isZero());
    }

    // -------------------------------------------------------------------------
    // archive() / restore()
    // -------------------------------------------------------------------------

    public function testArchiveThrowsStockNotEmptyOnArchiveExceptionWhenTotalOnHandPositive(): void
    {
        $this->expectException(StockNotEmptyOnArchiveException::class);

        $stockItem = $this->openStockItemWithLot(self::LOT_ID_1, 'LOT-001', '10.000000', '+6 months');
        $_         = $stockItem->pullDomainEvents();

        $stockItem->archive($this->now);
    }

    public function testArchiveSucceedsWhenTotalOnHandIsZero(): void
    {
        $stockItem = $this->openStockItem();
        $_         = $stockItem->pullDomainEvents();

        $stockItem->archive($this->now);

        $events = $stockItem->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(StockItemArchived::class, $events[0]);
        self::assertSame(StockItemStatus::ARCHIVED, $stockItem->status());
    }

    public function testRestoreIsIdempotentWhenAlreadyActive(): void
    {
        $stockItem = $this->openStockItem();
        $_         = $stockItem->pullDomainEvents();

        // Already ACTIVE — restore is a no-op
        $stockItem->restore($this->now);

        $events = $stockItem->pullDomainEvents();

        self::assertSame([], $events);
        self::assertSame(StockItemStatus::ACTIVE, $stockItem->status());
    }

    public function testAnyMutationThrowsArchivedStockItemExceptionOnArchivedItem(): void
    {
        $this->expectException(ArchivedStockItemException::class);

        $stockItem = $this->openStockItem();
        $_         = $stockItem->pullDomainEvents();
        $stockItem->archive($this->now);
        $_ = $stockItem->pullDomainEvents();

        $stockItem->receiveStock(
            LotNumber::fromString('LOT-001'),
            Quantity::of('5.000000', $this->unit),
            new \DateTimeImmutable('+6 months'),
            null,
            $this->now,
            $this->now,
            LotId::fromString(self::LOT_ID_1),
        );
    }

    // -------------------------------------------------------------------------
    // Invariant assertions
    // -------------------------------------------------------------------------

    public function testInvariantSumLotsEqualsTotalOnHandAfterReceiveStock(): void
    {
        $stockItem = $this->openStockItem();
        $_         = $stockItem->pullDomainEvents();

        $this->receiveInto($stockItem, self::LOT_ID_1, 'LOT-001', '10.000000', '+6 months');
        $this->receiveInto($stockItem, self::LOT_ID_2, 'LOT-002', '7.000000', '+9 months');
        $_ = $stockItem->pullDomainEvents();

        $this->assertLotsSumEqualsTotalOnHand($stockItem);
    }

    public function testInvariantSumLotsEqualsTotalOnHandAfterConsumeStock(): void
    {
        $stockItem = $this->openStockItem();
        $_         = $stockItem->pullDomainEvents();

        $this->receiveInto($stockItem, self::LOT_ID_1, 'LOT-001', '10.000000', '+2 months');
        $this->receiveInto($stockItem, self::LOT_ID_2, 'LOT-002', '7.000000', '+9 months');
        $_ = $stockItem->pullDomainEvents();

        $stockItem->consumeStock(Quantity::of('13.000000', $this->unit), $this->fefo, $this->now, $this->now);
        $_ = $stockItem->pullDomainEvents();

        $this->assertLotsSumEqualsTotalOnHand($stockItem);
    }

    public function testInvariantSumLotsEqualsTotalOnHandAfterAdjustStock(): void
    {
        $stockItem = $this->openStockItemWithLot(self::LOT_ID_1, 'LOT-001', '10.000000', '+6 months');
        $_         = $stockItem->pullDomainEvents();

        $stockItem->adjustStock(
            LotId::fromString(self::LOT_ID_1),
            Quantity::of('4.000000', $this->unit),
            'adjustment',
            $this->now,
            $this->now,
        );
        $_ = $stockItem->pullDomainEvents();

        $this->assertLotsSumEqualsTotalOnHand($stockItem);
    }

    // -------------------------------------------------------------------------
    // AC-06: multi-lot event ordering
    // -------------------------------------------------------------------------

    public function testConsumeStockMultiLotEventOrder(): void
    {
        // L1 expires sooner (Jan 1), L2 expires later (Mar 1)
        $stockItem = $this->openStockItem();
        $_         = $stockItem->pullDomainEvents();

        $this->receiveInto($stockItem, self::LOT_ID_1, 'LOT-L1', '5.000000', '2026-01-01');
        $this->receiveInto($stockItem, self::LOT_ID_2, 'LOT-L2', '3.000000', '2026-03-01');
        $_ = $stockItem->pullDomainEvents();

        // Consume 6: L1 fully depleted (5) + L2 partially (1)
        $consumptions = $stockItem->consumeStock(
            Quantity::of('6.000000', $this->unit),
            $this->fefo,
            $this->now,
            $this->now,
        );

        $events = $stockItem->pullDomainEvents();

        // Expected: [LotDepleted(L1), StockConsumed(L1, 5), StockConsumed(L2, 1)]
        self::assertCount(3, $events);
        self::assertInstanceOf(LotDepleted::class, $events[0]);
        self::assertInstanceOf(StockConsumed::class, $events[1]);
        self::assertInstanceOf(StockConsumed::class, $events[2]);

        $depleted = $events[0];
        self::assertInstanceOf(LotDepleted::class, $depleted);
        self::assertSame(self::LOT_ID_1, $depleted->lotId);

        $consumed1 = $events[1];
        self::assertInstanceOf(StockConsumed::class, $consumed1);
        self::assertSame(self::LOT_ID_1, $consumed1->lotId);
        self::assertSame('5.000000', $consumed1->quantityAmount);

        $consumed2 = $events[2];
        self::assertInstanceOf(StockConsumed::class, $consumed2);
        self::assertSame(self::LOT_ID_2, $consumed2->lotId);
        self::assertSame('1.000000', $consumed2->quantityAmount);

        // consumptions VO list
        self::assertCount(2, $consumptions);
        self::assertSame(self::LOT_ID_1, $consumptions[0]->lotId->toString());
        self::assertSame('5.000000', $consumptions[0]->quantity->amount());
        self::assertSame(self::LOT_ID_2, $consumptions[1]->lotId->toString());
        self::assertSame('1.000000', $consumptions[1]->quantity->amount());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function openStockItem(bool $trackStock = true): StockItem
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($this->now);

        return StockItem::open(
            StockItemId::fromString(self::STOCK_ITEM_ID),
            ClinicId::fromString(self::CLINIC_ID),
            ArticleId::fromString(self::ARTICLE_ID),
            $this->unit,
            new StockThreshold(
                Quantity::of('5.000000', $this->unit),
                StockThresholdType::ABSOLUTE,
            ),
            $trackStock,
            $clock,
        );
    }

    private function openStockItemWithLot(
        string $lotId,
        string $lotNumber,
        string $quantity,
        string $expiryDate,
    ): StockItem {
        $stockItem = $this->openStockItem();
        $_         = $stockItem->pullDomainEvents();

        $stockItem->receiveStock(
            LotNumber::fromString($lotNumber),
            Quantity::of($quantity, $this->unit),
            new \DateTimeImmutable($expiryDate),
            null,
            $this->now,
            $this->now,
            LotId::fromString($lotId),
        );
        $_ = $stockItem->pullDomainEvents();

        return $stockItem;
    }

    private function receiveInto(
        StockItem $stockItem,
        string $lotId,
        string $lotNumber,
        string $quantity,
        string $expiryDate,
    ): void {
        $stockItem->receiveStock(
            LotNumber::fromString($lotNumber),
            Quantity::of($quantity, $this->unit),
            new \DateTimeImmutable($expiryDate),
            null,
            $this->now,
            $this->now,
            LotId::fromString($lotId),
        );
    }

    private function assertLotsSumEqualsTotalOnHand(StockItem $stockItem): void
    {
        $sum = '0.000000';

        foreach ($stockItem->lots() as $lot) {
            if (LotStatus::ACTIVE === $lot->status()) {
                $sum = bcadd($sum, $lot->quantity()->amount(), 6);
            }
        }

        self::assertSame(
            $sum,
            $stockItem->totalOnHand()->amount(),
            'Sum of ACTIVE lot quantities must equal totalOnHand.',
        );
    }
}
