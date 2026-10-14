<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Application\Command;

use App\Context\Inventory\Application\Command\ExpireOutdatedLots\ExpireOutdatedLots;
use App\Context\Inventory\Application\Command\ExpireOutdatedLots\ExpireOutdatedLotsHandler;
use App\Context\Inventory\Application\Command\ReceiveStock\ReceiveStock;
use App\Context\Inventory\Application\Command\ReceiveStock\ReceiveStockHandler;
use App\Context\Inventory\Application\Command\RecordPhysicalInventory\RecordPhysicalInventory;
use App\Context\Inventory\Application\Command\RecordPhysicalInventory\RecordPhysicalInventoryHandler;
use App\Context\Inventory\Application\Port\StockItemReadRepositoryInterface;
use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\Entity\Lot;
use App\Context\Inventory\Domain\StockItem\Exception\ConcurrentStockModificationException;
use App\Context\Inventory\Domain\StockItem\Exception\StockItemNotFoundException;
use App\Context\Inventory\Domain\StockItem\Repository\StockItemRepositoryInterface;
use App\Context\Inventory\Domain\StockItem\StockItem;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotId;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotNumber;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotStatus;
use App\Context\Inventory\Domain\StockItem\ValueObject\Quantity;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemStatus;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockThreshold;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockThresholdType;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementReason;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementType;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;

#[CoversClass(ReceiveStockHandler::class)]
#[CoversClass(ReceiveStock::class)]
#[CoversClass(RecordPhysicalInventoryHandler::class)]
#[CoversClass(RecordPhysicalInventory::class)]
#[CoversClass(ExpireOutdatedLotsHandler::class)]
#[CoversClass(ExpireOutdatedLots::class)]
final class StockEntryHandlersTest extends InventoryCommandFixtures
{
    private const string SUPPLIER_ID = '01970000-0000-7000-8000-0000000000ff';

    public function testADeliveryFromASupplierIsAReceiptFromThatSupplier(): void
    {
        $item = $this->stockItem();

        $this->receive(new ReceiveStock(
            stockItemId: self::STOCK_ITEM_ID,
            clinicId: self::CLINIC_ID,
            lotNumber: 'LOT-NEW',
            quantityAmount: '40.000000',
            quantityUnit: 'TABLET',
            expiryDate: '2027-09-30',
            sourceSupplierId: self::SUPPLIER_ID,
            occurredAt: null,
            reference: 'BL-2026-001',
            performedBy: self::USER_ID,
        ));

        self::assertSame('40.000000', $item->totalOnHand()->amount());
        self::assertSame(MovementReason::RECEIPT_SUPPLIER, $this->written[0]->reason());
        self::assertSame('BL-2026-001', $this->written[0]->reference());
        self::assertSame(self::USER_ID, $this->written[0]->performedBy());
    }

    public function testStockFromNoSupplierIsAnOpeningOnTheDayItSays(): void
    {
        $this->stockItem();

        $this->receive(new ReceiveStock(self::STOCK_ITEM_ID, self::CLINIC_ID, 'LOT-OLD', '5.000000', 'TABLET', '2027-01-31', null, '2026-07-01T09:30:00+00:00', null, null));

        self::assertSame(MovementReason::RECEIPT_OPENING, $this->written[0]->reason());
        self::assertSame('2026-07-01', $this->written[0]->occurredAt()->format('Y-m-d'));
    }

    public function testAReceiptForAnUnknownItemIsRefused(): void
    {
        $this->expectException(StockItemNotFoundException::class);

        $this->receive(new ReceiveStock(self::STOCK_ITEM_ID, self::CLINIC_ID, 'LOT', '5.000000', 'TABLET', '2027-01-31', null, null, null, null));
    }

    public function testACountBelowTheShelfIsADecreasingAdjustmentUnderTheCountReference(): void
    {
        $item = $this->stockItem([[self::LOT_A, 'LOT-A', '20.000000', '2027-12-31']]);

        $this->tally(new RecordPhysicalInventory(
            stockItemId: self::STOCK_ITEM_ID,
            clinicId: self::CLINIC_ID,
            lotId: self::LOT_A,
            newLotQuantityAmount: '18.000000',
            newLotQuantityUnit: 'TABLET',
            performedBy: self::USER_ID,
            reference: 'INV-2026-08-21',
        ));

        self::assertSame('18.000000', $item->totalOnHand()->amount());
        self::assertSame(MovementType::ADJUSTMENT, $this->written[0]->type());
        self::assertTrue($this->written[0]->isDecrease());
        self::assertSame('2.000000', $this->written[0]->quantity()->amount());
        self::assertSame('INV-2026-08-21', $this->written[0]->reference());
    }

    public function testACorrectionBringsADepletedLotBack(): void
    {
        $item = $this->stockItem([[self::LOT_A, 'LOT-A', '20.000000', '2027-12-31']]);

        $this->tally(new RecordPhysicalInventory(self::STOCK_ITEM_ID, self::CLINIC_ID, self::LOT_A, '0.000000', 'TABLET'));
        $this->tally(new RecordPhysicalInventory(self::STOCK_ITEM_ID, self::CLINIC_ID, self::LOT_A, '20.000000', 'TABLET', 'CORRECTION', '2026-08-21T11:00:00+00:00'));

        self::assertSame(LotStatus::ACTIVE, $item->lots()[0]->status());
        self::assertSame('20.000000', $item->totalOnHand()->amount());
        self::assertSame(MovementReason::CORRECTION, $this->written[1]->reason());
        self::assertFalse($this->written[1]->isDecrease());
    }

    public function testAnAdjustmentUnderAnotherReasonTakesTheSenseOfItsDelta(): void
    {
        $this->stockItem([[self::LOT_A, 'LOT-A', '20.000000', '2027-12-31']]);

        $this->tally(new RecordPhysicalInventory(self::STOCK_ITEM_ID, self::CLINIC_ID, self::LOT_A, '19.000000', 'TABLET', 'LOSS_BREAKAGE'));
        $this->tally(new RecordPhysicalInventory(self::STOCK_ITEM_ID, self::CLINIC_ID, self::LOT_A, '21.000000', 'TABLET', 'RECEIPT_RETURN_FROM_CLIENT'));

        self::assertSame(MovementType::OUT, $this->written[0]->type());
        self::assertSame(MovementType::IN, $this->written[1]->type());
    }

    public function testACountThatMatchesTheShelfMovesNothing(): void
    {
        $this->stockItem([[self::LOT_A, 'LOT-A', '20.000000', '2027-12-31']]);

        $this->tally(new RecordPhysicalInventory(self::STOCK_ITEM_ID, self::CLINIC_ID, self::LOT_A, '20.000000', 'TABLET'));

        self::assertSame([], $this->written);
    }

    public function testACountOfAnUnknownItemIsRefused(): void
    {
        $this->expectException(StockItemNotFoundException::class);

        $this->tally(new RecordPhysicalInventory(self::STOCK_ITEM_ID, self::CLINIC_ID, self::LOT_A, '1.000000', 'TABLET'));
    }

    public function testTheNightlyExpiryWritesOneLossPerLotThatHeldSomething(): void
    {
        $item = $this->stockItem([[self::LOT_A, 'LOT-A', '4.000000', '2026-08-01'], [self::LOT_B, 'LOT-B', '30.000000', '2027-12-31']]);

        $processed = $this->expire([['stockItemId' => self::STOCK_ITEM_ID, 'clinicId' => self::CLINIC_ID]], $this->items, new ExpireOutdatedLots(self::CLINIC_ID));

        self::assertSame(1, $processed);
        self::assertSame(LotStatus::EXPIRED, $item->lots()[0]->status());
        self::assertCount(1, $this->written);
        self::assertSame(MovementReason::LOSS_EXPIRY, $this->written[0]->reason());
    }

    public function testTheNightlyExpirySkipsWhatItCannotFindOrHoldsNothing(): void
    {
        $unit = UnitOfMeasure::fromString('TABLET');
        $now  = self::now();

        // An outdated lot still active although empty: it expires, without a loss to write.
        $this->items->save(StockItem::reconstitute(
            id: StockItemId::fromString(self::STOCK_ITEM_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            articleId: ArticleId::fromString(self::ARTICLE_ID),
            unit: $unit,
            totalOnHand: Quantity::zero($unit),
            reservedQuantity: Quantity::zero($unit),
            lots: [new Lot(LotId::fromString(self::LOT_A), LotNumber::fromString('LOT-A'), Quantity::zero($unit), new \DateTimeImmutable('2026-08-01'), $now, null, LotStatus::ACTIVE, $now, $now)],
            minimumThreshold: new StockThreshold(Quantity::zero($unit), StockThresholdType::ABSOLUTE),
            trackStock: true,
            status: StockItemStatus::ACTIVE,
            createdAt: $now,
            updatedAt: $now,
        ));

        // Something else happened to the item earlier in the batch: only expiries make losses.
        $this->items->findById(StockItemId::fromString(self::STOCK_ITEM_ID), ClinicId::fromString(self::CLINIC_ID))?->relocate(\App\Context\Inventory\Domain\StockItem\ValueObject\StorageLocation::PHARMACY, $now);

        $processed = $this->expire([
            ['stockItemId' => '01970000-0000-7000-8000-0000000000ee', 'clinicId' => self::CLINIC_ID],
            ['stockItemId' => self::STOCK_ITEM_ID, 'clinicId' => self::CLINIC_ID],
        ], $this->items, new ExpireOutdatedLots());

        self::assertSame(1, $processed);
        self::assertSame([], $this->written);
    }

    public function testAConcurrentChangeIsLoggedAndSkipped(): void
    {
        $this->stockItem([[self::LOT_A, 'LOT-A', '4.000000', '2026-08-01']]);

        $held = $this->items->findById(StockItemId::fromString(self::STOCK_ITEM_ID), ClinicId::fromString(self::CLINIC_ID));
        $racy = $this->createStub(StockItemRepositoryInterface::class);
        $racy->method('findById')->willReturn($held);
        $racy->method('save')->willThrowException(new ConcurrentStockModificationException(self::STOCK_ITEM_ID));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $processed = $this->expire([['stockItemId' => self::STOCK_ITEM_ID, 'clinicId' => self::CLINIC_ID]], $racy, new ExpireOutdatedLots(), $logger);

        self::assertSame(0, $processed);
    }

    private function receive(ReceiveStock $command): void
    {
        new ReceiveStockHandler($this->items, $this->movements, $this->publisher(), $this->clock(), $this->entityManager())($command);
    }

    private function tally(RecordPhysicalInventory $command): void
    {
        new RecordPhysicalInventoryHandler($this->items, $this->movements, $this->publisher(), $this->clock(), $this->entityManager())($command);
    }

    /**
     * @param list<array{stockItemId: string, clinicId: string}> $pairs
     */
    private function expire(array $pairs, StockItemRepositoryInterface $items, ExpireOutdatedLots $command, ?LoggerInterface $logger = null): int
    {
        $read = $this->createStub(StockItemReadRepositoryInterface::class);
        $read->method('findItemsWithExpiredLots')->willReturn($pairs);

        return new ExpireOutdatedLotsHandler($items, $read, $this->movements, $this->publisher(), $this->clock(), $this->entityManager(), $logger ?? $this->createStub(LoggerInterface::class))($command);
    }
}
