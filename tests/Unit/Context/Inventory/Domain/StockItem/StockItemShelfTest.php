<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Domain\StockItem;

use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\Event\ExpiredLotDiscarded;
use App\Context\Inventory\Domain\StockItem\Event\LotDepleted;
use App\Context\Inventory\Domain\StockItem\Event\LotExpired;
use App\Context\Inventory\Domain\StockItem\Event\StockConsumed;
use App\Context\Inventory\Domain\StockItem\Event\StockItemRelocated;
use App\Context\Inventory\Domain\StockItem\Exception\ArchivedStockItemException;
use App\Context\Inventory\Domain\StockItem\Exception\InsufficientStockException;
use App\Context\Inventory\Domain\StockItem\Exception\LotAlreadyTerminatedException;
use App\Context\Inventory\Domain\StockItem\Exception\LotExpiredException;
use App\Context\Inventory\Domain\StockItem\Exception\LotNotExpiredException;
use App\Context\Inventory\Domain\StockItem\Exception\LotNotFoundException;
use App\Context\Inventory\Domain\StockItem\StockItem;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotId;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotNumber;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotStatus;
use App\Context\Inventory\Domain\StockItem\ValueObject\Quantity;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockThreshold;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockThresholdType;
use App\Context\Inventory\Domain\StockItem\ValueObject\StorageLocation;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Tests\Shared\Time\FrozenClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * What the stock page does to a shelf: take from the lot actually opened,
 * bin what is past its date, count a found box back in, say where the
 * article is kept.
 */
#[CoversClass(StockItem::class)]
#[CoversClass(ExpiredLotDiscarded::class)]
#[CoversClass(StockItemRelocated::class)]
#[CoversClass(LotNotExpiredException::class)]
final class StockItemShelfTest extends TestCase
{
    private const string ID    = '01970002-0000-7000-8000-000000000001';
    private const string LOT_A = '01970002-0000-7000-8000-00000000000a';
    private const string LOT_B = '01970002-0000-7000-8000-00000000000b';
    private const string NOPE  = '01970002-0000-7000-8000-0000000000ff';

    private UnitOfMeasure $unit;

    private \DateTimeImmutable $today;

    protected function setUp(): void
    {
        $this->unit  = UnitOfMeasure::fromString('TABLET');
        $this->today = new \DateTimeImmutable('2026-08-21 10:00:00');
    }

    public function testAnItemOpensWhereItIsKeptAndKeepsItsPlaceWhenReconstituted(): void
    {
        $item = $this->item(location: StorageLocation::FRIDGE);

        self::assertSame(StorageLocation::FRIDGE, $item->location());

        $copy = StockItem::reconstitute(
            id: $item->id(),
            clinicId: $item->clinicId(),
            articleId: $item->articleId(),
            unit: $item->unit(),
            totalOnHand: $item->totalOnHand(),
            reservedQuantity: $item->reservedQuantity(),
            lots: $item->lots(),
            minimumThreshold: $item->minimumThreshold(),
            trackStock: true,
            status: $item->status(),
            createdAt: $item->createdAt(),
            updatedAt: $item->updatedAt(),
            location: StorageLocation::OPERATING_ROOM,
        );

        self::assertSame(StorageLocation::OPERATING_ROOM, $copy->location());
    }

    public function testRelocatingAnnouncesTheNewPlaceOnlyWhenItChanges(): void
    {
        $item = $this->item();

        $item->relocate(StorageLocation::RECEPTION, $this->today);
        $item->relocate(StorageLocation::RECEPTION, $this->today);

        $events = array_values(array_filter($item->pullDomainEvents(), static fn ($e): bool => $e instanceof StockItemRelocated));
        self::assertCount(1, $events);
        self::assertSame(self::ID, $events[0]->aggregateId());
        self::assertSame('RECEPTION', $events[0]->payload()['location']);
        self::assertSame($this->today, $item->updatedAt());
    }

    public function testAnArchivedItemCannotMove(): void
    {
        $item = $this->item();
        $item->archive($this->today);

        $this->expectException(ArchivedStockItemException::class);

        $item->relocate(StorageLocation::PHARMACY, $this->today);
    }

    public function testTakingFromAChosenLotLeavesTheOthersAlone(): void
    {
        $item = $this->item([[self::LOT_A, 'A', '10', '2026-12-31'], [self::LOT_B, 'B', '10', '2027-12-31']]);

        $taken = $item->consumeFromLot(LotId::fromString(self::LOT_B), $this->qty('4'), $this->today, $this->today);

        self::assertCount(1, $taken);
        self::assertSame('4.000000', $taken[0]->quantity->amount());
        self::assertSame('10.000000', $item->lots()[0]->quantity()->amount());
        self::assertSame('6.000000', $item->lots()[1]->quantity()->amount());
        self::assertSame('16.000000', $item->totalOnHand()->amount());
        self::assertNotEmpty(array_filter($item->pullDomainEvents(), static fn ($e): bool => $e instanceof StockConsumed));
    }

    public function testTakingAllOfALotDepletesIt(): void
    {
        $item = $this->item([[self::LOT_A, 'A', '10', '2026-12-31']]);

        $item->consumeFromLot(LotId::fromString(self::LOT_A), $this->qty('10'), $this->today, $this->today);

        self::assertSame(LotStatus::DEPLETED, $item->lots()[0]->status());
        self::assertNotEmpty(array_filter($item->pullDomainEvents(), static fn ($e): bool => $e instanceof LotDepleted));
    }

    public function testAnUntrackedItemTakesNothingFromALot(): void
    {
        $item = $this->item(trackStock: false);

        self::assertSame([], $item->consumeFromLot(LotId::fromString(self::LOT_A), $this->qty('1'), $this->today, $this->today));
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: class-string<\Throwable>}>
     */
    public static function provideAWithdrawalTheLotCannotGiveIsRefusedCases(): iterable
    {
        yield 'an unknown lot' => [self::NOPE, '1', LotNotFoundException::class];
        yield 'more than the lot holds' => [self::LOT_A, '11', InsufficientStockException::class];
        yield 'a lot past its date' => [self::LOT_B, '1', LotExpiredException::class];
    }

    /**
     * @param class-string<\Throwable> $exception
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideAWithdrawalTheLotCannotGiveIsRefusedCases')]
    public function testAWithdrawalTheLotCannotGiveIsRefused(string $lotId, string $quantity, string $exception): void
    {
        $item = $this->item([[self::LOT_A, 'A', '10', '2026-12-31'], [self::LOT_B, 'B', '5', '2026-08-01']]);

        $this->expectException($exception);

        $item->consumeFromLot(LotId::fromString($lotId), $this->qty($quantity), $this->today, $this->today);
    }

    public function testATerminatedLotGivesNothing(): void
    {
        $item = $this->item([[self::LOT_A, 'A', '10', '2026-12-31']]);
        $item->markLotAsRecalled(LotId::fromString(self::LOT_A), 'Rappel', $this->today, $this->today);

        $this->expectException(LotAlreadyTerminatedException::class);

        $item->consumeFromLot(LotId::fromString(self::LOT_A), $this->qty('1'), $this->today, $this->today);
    }

    public function testAnArchivedItemGivesNothing(): void
    {
        $item = $this->item();
        $item->archive($this->today);

        $this->expectException(ArchivedStockItemException::class);

        $item->consumeFromLot(LotId::fromString(self::LOT_A), $this->qty('1'), $this->today, $this->today);
    }

    public function testBinningALotPastItsDateExpiresItFirst(): void
    {
        $item = $this->item([[self::LOT_A, 'A', '4', '2026-08-01'], [self::LOT_B, 'B', '6', '2027-12-31']]);

        $expired = $item->discardExpiredLot(LotId::fromString(self::LOT_A), $this->today, $this->today);

        self::assertSame('4.000000', $expired->amount());
        self::assertSame(LotStatus::EXPIRED, $item->lots()[0]->status());
        self::assertTrue($item->lots()[0]->quantity()->isZero());
        self::assertSame('6.000000', $item->totalOnHand()->amount());

        $events = $item->pullDomainEvents();
        self::assertNotEmpty(array_filter($events, static fn ($e): bool => $e instanceof LotExpired));

        $discarded = array_values(array_filter($events, static fn ($e): bool => $e instanceof ExpiredLotDiscarded));
        self::assertCount(1, $discarded);
        self::assertSame(self::ID, $discarded[0]->aggregateId());
        self::assertSame('4.000000', $discarded[0]->payload()['discardedQuantityAmount']);
    }

    public function testBinningALotAlreadyExpiredOnlyClearsTheShelf(): void
    {
        $item = $this->item([[self::LOT_A, 'A', '4', '2026-08-01']]);
        $item->expireOutdatedLots($this->today, $this->today);

        $expired = $item->discardExpiredLot(LotId::fromString(self::LOT_A), $this->today, $this->today);

        self::assertTrue($expired->isZero());
        self::assertTrue($item->lots()[0]->quantity()->isZero());
        self::assertTrue($item->totalOnHand()->isZero());
    }

    public function testALotStillInDateCannotBeBinnedAsExpired(): void
    {
        $item = $this->item([[self::LOT_A, 'A', '4', '2027-08-01']]);

        $this->expectException(LotNotExpiredException::class);

        $item->discardExpiredLot(LotId::fromString(self::LOT_A), $this->today, $this->today);
    }

    public function testAnExpiredLotAlreadyBinnedIsTerminated(): void
    {
        $item = $this->item([[self::LOT_A, 'A', '4', '2026-08-01']]);
        $item->discardExpiredLot(LotId::fromString(self::LOT_A), $this->today, $this->today);

        $this->expectException(LotAlreadyTerminatedException::class);

        $item->discardExpiredLot(LotId::fromString(self::LOT_A), $this->today, $this->today);
    }

    public function testBinningAnUnknownLotOrOnAnArchivedItemIsRefused(): void
    {
        $item = $this->item();

        try {
            $item->discardExpiredLot(LotId::fromString(self::NOPE), $this->today, $this->today);
            self::fail('An unknown lot was binned.');
        } catch (LotNotFoundException) {
        }

        $item->archive($this->today);

        $this->expectException(ArchivedStockItemException::class);

        $item->discardExpiredLot(LotId::fromString(self::NOPE), $this->today, $this->today);
    }

    public function testACountBringsADepletedLotBackAndAnotherReceiptTopsItUp(): void
    {
        $item = $this->item([[self::LOT_A, 'A', '4', '2027-08-01']]);
        $item->consumeFromLot(LotId::fromString(self::LOT_A), $this->qty('4'), $this->today, $this->today);

        $item->adjustStock(LotId::fromString(self::LOT_A), $this->qty('2'), 'CORRECTION', $this->today, $this->today);
        self::assertSame(LotStatus::ACTIVE, $item->lots()[0]->status());
        self::assertSame('2.000000', $item->totalOnHand()->amount());

        $item->adjustStock(LotId::fromString(self::LOT_A), $this->qty('0'), 'CORRECTION', $this->today, $this->today);
        self::assertSame(LotStatus::DEPLETED, $item->lots()[0]->status());

        $item->adjustStock(LotId::fromString(self::LOT_A), $this->qty('0'), 'CORRECTION', $this->today, $this->today);
        self::assertSame(LotStatus::DEPLETED, $item->lots()[0]->status());

        $item->receiveStock(LotNumber::fromString('A'), $this->qty('3'), new \DateTimeImmutable('2027-08-01'), null, $this->today, $this->today, LotId::fromString(self::LOT_B));
        self::assertSame(LotStatus::ACTIVE, $item->lots()[0]->status());
        self::assertSame('3.000000', $item->totalOnHand()->amount());
        self::assertCount(1, $item->lots());
    }

    public function testACountCannotBringBackARecalledLot(): void
    {
        $item = $this->item([[self::LOT_A, 'A', '4', '2027-08-01']]);
        $item->markLotAsRecalled(LotId::fromString(self::LOT_A), 'Rappel', $this->today, $this->today);

        $this->expectException(LotAlreadyTerminatedException::class);

        $item->adjustStock(LotId::fromString(self::LOT_A), $this->qty('2'), 'CORRECTION', $this->today, $this->today);
    }

    public function testAnUntrackedItemIgnoresCountsAndAnUnknownLotCannotBeCounted(): void
    {
        $untracked = $this->item(trackStock: false);
        $untracked->adjustStock(LotId::fromString(self::NOPE), $this->qty('3'), 'CORRECTION', $this->today, $this->today);
        self::assertTrue($untracked->totalOnHand()->isZero());

        $this->expectException(LotNotFoundException::class);

        $this->item()->adjustStock(LotId::fromString(self::NOPE), $this->qty('3'), 'CORRECTION', $this->today, $this->today);
    }

    public function testAWithdrawalThatEmptiesTheFirstLotStopsThere(): void
    {
        $item = $this->item([[self::LOT_A, 'A', '5', '2026-12-31'], [self::LOT_B, 'B', '5', '2027-12-31']]);

        $taken = $item->consumeStock($this->qty('5'), new \App\Context\Inventory\Domain\StockItem\Service\FefoSelector(), $this->today, $this->today);

        self::assertCount(1, $taken);
        self::assertSame('5.000000', $item->lots()[1]->quantity()->amount());
    }

    public function testReservationsHoldStockBackAndAreReleased(): void
    {
        $item = $this->item([[self::LOT_A, 'A', '5', '2026-12-31']]);

        $item->reserveStock($this->qty('2'), $this->today);
        self::assertSame('3.000000', $item->availableQuantity()->amount());

        $item->releaseReservation($this->qty('2'), $this->today);
        self::assertSame('5.000000', $item->availableQuantity()->amount());

        $untracked = $this->item(trackStock: false);
        $untracked->reserveStock($this->qty('2'), $this->today);
        $untracked->releaseReservation($this->qty('2'), $this->today);
        self::assertTrue($untracked->reservedQuantity()->isZero());

        try {
            $item->reserveStock($this->qty('9'), $this->today);
            self::fail('More than available was reserved.');
        } catch (InsufficientStockException) {
        }

        $this->expectException(\App\Context\Inventory\Domain\StockItem\Exception\NegativeQuantityException::class);

        $item->releaseReservation($this->qty('1'), $this->today);
    }

    public function testAnUntrackedItemOnlyRecordsWhatWasReceived(): void
    {
        $item = $this->item(trackStock: false);

        self::assertNull($item->receiveStock(LotNumber::fromString('A'), $this->qty('3'), new \DateTimeImmutable('2027-01-01'), null, $this->today, $this->today, LotId::fromString(self::LOT_A)));
        self::assertSame([], $item->lots());
        self::assertCount(1, $item->pullDomainEvents());
    }

    public function testAStoppedFollowUpComesBackOnce(): void
    {
        $item = $this->item();
        $item->archive($this->today);
        $item->restore($this->today);
        $item->restore($this->today);

        $restored = array_filter($item->pullDomainEvents(), static fn ($e): bool => $e instanceof \App\Context\Inventory\Domain\StockItem\Event\StockItemRestored);
        self::assertCount(1, $restored);
        self::assertSame(\App\Context\Inventory\Domain\StockItem\ValueObject\StockItemStatus::ACTIVE, $item->status());
    }

    public function testRecallingAnUnknownLotIsRefused(): void
    {
        $this->expectException(LotNotFoundException::class);

        $this->item()->markLotAsRecalled(LotId::fromString(self::NOPE), 'Rappel', $this->today, $this->today);
    }

    public function testAShelfThatDoesNotAddUpIsCaught(): void
    {
        $lot = new \App\Context\Inventory\Domain\StockItem\Entity\Lot(LotId::fromString(self::LOT_A), LotNumber::fromString('A'), $this->qty('4'), new \DateTimeImmutable('2027-01-01'), $this->today, null, LotStatus::ACTIVE, $this->today, $this->today);

        // An untracked item does not check its lots.
        $this->reconstituted([$lot], '9', trackStock: false)->markLotAsRecalled(LotId::fromString(self::LOT_A), 'Rappel', $this->today, $this->today);

        // A tracked one whose total says more than its lots hold has nothing to give…
        $empty = $this->reconstituted([], '4');

        try {
            $empty->consumeStock($this->qty('2'), new \App\Context\Inventory\Domain\StockItem\Service\FefoSelector(), $this->today, $this->today);
            self::fail('Stock was taken from no lot.');
        } catch (InsufficientStockException) {
        }

        // …and refuses to go on once a change shows the gap.
        $this->expectException(\LogicException::class);

        $this->reconstituted([$lot], '9')->consumeFromLot(LotId::fromString(self::LOT_A), $this->qty('1'), $this->today, $this->today);
    }

    /**
     * @param list<\App\Context\Inventory\Domain\StockItem\Entity\Lot> $lots
     */
    private function reconstituted(array $lots, string $onHand, bool $trackStock = true): StockItem
    {
        return StockItem::reconstitute(
            id: StockItemId::fromString(self::ID),
            clinicId: ClinicId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            articleId: ArticleId::fromString('550e8400-e29b-41d4-a716-446655440001'),
            unit: $this->unit,
            totalOnHand: $this->qty($onHand),
            reservedQuantity: $this->qty('0'),
            lots: $lots,
            minimumThreshold: new StockThreshold($this->qty('0'), StockThresholdType::ABSOLUTE),
            trackStock: $trackStock,
            status: \App\Context\Inventory\Domain\StockItem\ValueObject\StockItemStatus::ACTIVE,
            createdAt: $this->today,
            updatedAt: $this->today,
        );
    }

    /**
     * @param list<array{0: string, 1: string, 2: string, 3: string}> $lots
     */
    private function item(array $lots = [], bool $trackStock = true, ?StorageLocation $location = null): StockItem
    {
        $item = StockItem::open(
            id: StockItemId::fromString(self::ID),
            clinicId: ClinicId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            articleId: ArticleId::fromString('550e8400-e29b-41d4-a716-446655440001'),
            unit: $this->unit,
            threshold: new StockThreshold($this->qty('0'), StockThresholdType::ABSOLUTE),
            trackStock: $trackStock,
            clock: new FrozenClock($this->today->modify('-90 days')),
            location: $location,
        );

        foreach ($lots as [$id, $number, $quantity, $expiry]) {
            $item->receiveStock(
                LotNumber::fromString($number),
                $this->qty($quantity),
                new \DateTimeImmutable($expiry),
                null,
                $this->today->modify('-60 days'),
                $this->today->modify('-60 days'),
                LotId::fromString($id),
            );
        }

        $_ = $item->pullDomainEvents();

        return $item;
    }

    private function qty(string $amount): Quantity
    {
        \assert(is_numeric($amount));

        return Quantity::of(bcadd($amount, '0', 6), $this->unit);
    }
}
