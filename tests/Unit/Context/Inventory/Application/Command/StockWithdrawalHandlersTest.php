<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Application\Command;

use App\Context\Inventory\Application\Command\ConsumeStock\ConsumeStock;
use App\Context\Inventory\Application\Command\ConsumeStock\ConsumeStockHandler;
use App\Context\Inventory\Application\Command\DiscardExpiredLot\DiscardExpiredLot;
use App\Context\Inventory\Application\Command\DiscardExpiredLot\DiscardExpiredLotHandler;
use App\Context\Inventory\Application\Command\MarkLotAsRecalled\MarkLotAsRecalled;
use App\Context\Inventory\Application\Command\MarkLotAsRecalled\MarkLotAsRecalledHandler;
use App\Context\Inventory\Domain\StockItem\Event\ExpiredLotDiscarded;
use App\Context\Inventory\Domain\StockItem\Event\LotRecalled;
use App\Context\Inventory\Domain\StockItem\Event\StockConsumed;
use App\Context\Inventory\Domain\StockItem\Exception\StockItemNotFoundException;
use App\Context\Inventory\Domain\StockItem\Service\FefoSelector;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotStatus;
use App\Context\Inventory\Domain\StockMovement\Event\StockMovementRecorded;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementReason;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementType;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ConsumeStockHandler::class)]
#[CoversClass(ConsumeStock::class)]
#[CoversClass(DiscardExpiredLotHandler::class)]
#[CoversClass(DiscardExpiredLot::class)]
#[CoversClass(MarkLotAsRecalledHandler::class)]
#[CoversClass(MarkLotAsRecalled::class)]
final class StockWithdrawalHandlersTest extends InventoryCommandFixtures
{
    public function testAWithdrawalFromAChosenLotTakesFromThatLotOnly(): void
    {
        $item = $this->stockItem([[self::LOT_A, 'LOT-A', '20.000000', '2026-12-31'], [self::LOT_B, 'LOT-B', '30.000000', '2027-12-31']]);

        $this->consume(new ConsumeStock(
            stockItemId: self::STOCK_ITEM_ID,
            clinicId: self::CLINIC_ID,
            quantityAmount: '5.000000',
            quantityUnit: 'TABLET',
            occurredAt: null,
            reference: 'Rocky (Bernard)',
            performedBy: self::USER_ID,
            note: 'Dr Lambert',
            reason: 'CONSUMPTION_INTERNAL',
            lotId: self::LOT_B,
        ));

        self::assertSame('20.000000', $item->lots()[0]->quantity()->amount());
        self::assertSame('25.000000', $item->lots()[1]->quantity()->amount());
        self::assertCount(1, $this->written);
        self::assertSame(MovementReason::CONSUMPTION_INTERNAL, $this->written[0]->reason());
        self::assertSame(MovementType::OUT, $this->written[0]->type());
        self::assertSame(self::LOT_B, $this->written[0]->lotId()?->toString());
        self::assertSame(self::now()->format(\DateTimeInterface::ATOM), $this->written[0]->occurredAt()->format(\DateTimeInterface::ATOM));
        self::assertSame('Rocky (Bernard)', $this->written[0]->reference());
        self::assertSame(self::USER_ID, $this->written[0]->performedBy());
        self::assertCount(1, $this->announcedOf(StockConsumed::class));
        self::assertCount(1, $this->announcedOf(StockMovementRecorded::class));
    }

    public function testAWithdrawalWithoutALotFollowsTheExpiryOrder(): void
    {
        $item = $this->stockItem([[self::LOT_A, 'LOT-A', '4.000000', '2026-12-31'], [self::LOT_B, 'LOT-B', '30.000000', '2027-12-31']]);

        $this->consume(new ConsumeStock(
            stockItemId: self::STOCK_ITEM_ID,
            clinicId: self::CLINIC_ID,
            quantityAmount: '6.000000',
            quantityUnit: 'TABLET',
            occurredAt: '2026-08-20T09:00:00+00:00',
            reference: null,
            performedBy: null,
            note: null,
        ));

        self::assertSame(LotStatus::DEPLETED, $item->lots()[0]->status());
        self::assertSame('28.000000', $item->lots()[1]->quantity()->amount());
        self::assertCount(2, $this->written);
        self::assertSame(MovementReason::CONSUMPTION_CONSULTATION, $this->written[0]->reason());
        self::assertSame('2026-08-20', $this->written[0]->occurredAt()->format('Y-m-d'));
    }

    public function testAnUntrackedArticleRecordsNoMovement(): void
    {
        $this->stockItem([], trackStock: false);

        $this->consume(new ConsumeStock(self::STOCK_ITEM_ID, self::CLINIC_ID, '1.000000', 'TABLET', null, null, null, null));

        self::assertSame([], $this->written);
    }

    public function testAWithdrawalFromAnUnknownItemIsRefused(): void
    {
        $this->expectException(StockItemNotFoundException::class);

        $this->consume(new ConsumeStock(self::STOCK_ITEM_ID, self::CLINIC_ID, '1.000000', 'TABLET', null, null, null, null));
    }

    public function testDiscardingALotPastItsDateExpiresItAndWritesTheLoss(): void
    {
        $item = $this->stockItem([[self::LOT_A, 'LOT-A', '4.000000', '2026-08-01'], [self::LOT_B, 'LOT-B', '30.000000', '2027-12-31']]);

        $this->discard(new DiscardExpiredLot(self::STOCK_ITEM_ID, self::CLINIC_ID, self::LOT_A, self::USER_ID));

        self::assertSame(LotStatus::EXPIRED, $item->lots()[0]->status());
        self::assertTrue($item->lots()[0]->quantity()->isZero());
        self::assertSame('30.000000', $item->totalOnHand()->amount());
        self::assertCount(1, $this->written);
        self::assertSame(MovementReason::LOSS_EXPIRY, $this->written[0]->reason());
        self::assertSame('4.000000', $this->written[0]->quantity()->amount());
        self::assertSame(self::USER_ID, $this->written[0]->performedBy());
        self::assertCount(1, $this->announcedOf(ExpiredLotDiscarded::class));
    }

    public function testDiscardingALotTheNightlyJobExpiredMovesNothing(): void
    {
        $item = $this->stockItem([[self::LOT_A, 'LOT-A', '4.000000', '2026-08-01']]);
        $item->expireOutdatedLots(self::now(), self::now());
        $_ = $item->pullDomainEvents();

        $this->discard(new DiscardExpiredLot(self::STOCK_ITEM_ID, self::CLINIC_ID, self::LOT_A));

        self::assertTrue($item->lots()[0]->quantity()->isZero());
        self::assertSame([], $this->written);
        self::assertCount(1, $this->announcedOf(ExpiredLotDiscarded::class));
    }

    public function testDiscardingOnAnUnknownItemIsRefused(): void
    {
        $this->expectException(StockItemNotFoundException::class);

        $this->discard(new DiscardExpiredLot(self::STOCK_ITEM_ID, self::CLINIC_ID, self::LOT_A));
    }

    public function testARecallReturnsWhatIsLeftAndKeepsWhoDidIt(): void
    {
        $item = $this->stockItem([[self::LOT_A, 'LOT-A', '6.000000', '2027-08-01']]);

        $this->recall(new MarkLotAsRecalled(self::STOCK_ITEM_ID, self::CLINIC_ID, self::LOT_A, 'Rappel laboratoire', null, self::USER_ID));

        self::assertSame(LotStatus::RECALLED, $item->lots()[0]->status());
        self::assertCount(1, $this->written);
        self::assertSame(MovementReason::RECALL_RETURN_TO_SUPPLIER, $this->written[0]->reason());
        self::assertSame('Rappel laboratoire', $this->written[0]->note());
        self::assertSame(self::USER_ID, $this->written[0]->performedBy());
        self::assertCount(1, $this->announcedOf(LotRecalled::class));
    }

    public function testRecallingAnEmptyLotWritesNoMovement(): void
    {
        $this->stockItem([[self::LOT_A, 'LOT-A', '6.000000', '2027-08-01']]);
        $this->consume(new ConsumeStock(self::STOCK_ITEM_ID, self::CLINIC_ID, '6.000000', 'TABLET', null, null, null, null, 'SALE_RETAIL', self::LOT_A));
        $this->written = [];

        $this->recall(new MarkLotAsRecalled(self::STOCK_ITEM_ID, self::CLINIC_ID, self::LOT_A, 'Rappel', '2026-08-21T08:00:00+00:00'));

        self::assertSame([], $this->written);
    }

    public function testRecallingOnAnUnknownItemIsRefused(): void
    {
        $this->expectException(StockItemNotFoundException::class);

        $this->recall(new MarkLotAsRecalled(self::STOCK_ITEM_ID, self::CLINIC_ID, self::LOT_A, 'Rappel'));
    }

    private function consume(ConsumeStock $command): void
    {
        new ConsumeStockHandler($this->items, $this->movements, $this->publisher(), $this->clock(), $this->entityManager(), new FefoSelector())($command);
    }

    private function discard(DiscardExpiredLot $command): void
    {
        new DiscardExpiredLotHandler($this->items, $this->movements, $this->publisher(), $this->clock(), $this->entityManager())($command);
    }

    private function recall(MarkLotAsRecalled $command): void
    {
        new MarkLotAsRecalledHandler($this->items, $this->movements, $this->publisher(), $this->clock(), $this->entityManager())($command);
    }
}
