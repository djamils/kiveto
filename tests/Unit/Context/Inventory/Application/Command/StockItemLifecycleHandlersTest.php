<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Application\Command;

use App\Context\Inventory\Application\Command\ArchiveStockItem\ArchiveStockItem;
use App\Context\Inventory\Application\Command\ArchiveStockItem\ArchiveStockItemHandler;
use App\Context\Inventory\Application\Command\ChangeStockThreshold\ChangeStockThreshold;
use App\Context\Inventory\Application\Command\ChangeStockThreshold\ChangeStockThresholdHandler;
use App\Context\Inventory\Application\Command\OpenStockItem\OpenStockItem;
use App\Context\Inventory\Application\Command\OpenStockItem\OpenStockItemHandler;
use App\Context\Inventory\Application\Command\RelocateStockItem\RelocateStockItem;
use App\Context\Inventory\Application\Command\RelocateStockItem\RelocateStockItemHandler;
use App\Context\Inventory\Application\Command\RestoreStockItem\RestoreStockItem;
use App\Context\Inventory\Application\Command\RestoreStockItem\RestoreStockItemHandler;
use App\Context\Inventory\Application\Exception\ArticleNotActiveException;
use App\Context\Inventory\Application\Exception\ArticleNotFoundException;
use App\Context\Inventory\Application\Port\ArticleProviderInterface;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\Event\StockItemArchived;
use App\Context\Inventory\Domain\StockItem\Event\StockItemRelocated;
use App\Context\Inventory\Domain\StockItem\Event\StockItemRestored;
use App\Context\Inventory\Domain\StockItem\Event\StockThresholdChanged;
use App\Context\Inventory\Domain\StockItem\Exception\DuplicateStockItemException;
use App\Context\Inventory\Domain\StockItem\Exception\StockItemNotFoundException;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemStatus;
use App\Context\Inventory\Domain\StockItem\ValueObject\StorageLocation;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(OpenStockItemHandler::class)]
#[CoversClass(OpenStockItem::class)]
#[CoversClass(RelocateStockItemHandler::class)]
#[CoversClass(RelocateStockItem::class)]
#[CoversClass(ChangeStockThresholdHandler::class)]
#[CoversClass(ChangeStockThreshold::class)]
#[CoversClass(ArchiveStockItemHandler::class)]
#[CoversClass(ArchiveStockItem::class)]
#[CoversClass(RestoreStockItemHandler::class)]
#[CoversClass(RestoreStockItem::class)]
final class StockItemLifecycleHandlersTest extends InventoryCommandFixtures
{
    public function testAnArticleIsFollowedWhereItIsKept(): void
    {
        $id = $this->open($this->catalogue(exists: true, active: true), new OpenStockItem(self::CLINIC_ID, self::ARTICLE_ID, 'TABLET', '5.000000', 'TABLET', 'ABSOLUTE', true, 'FRIDGE'));

        $item = $this->items->findById(StockItemId::fromString($id), ClinicId::fromString(self::CLINIC_ID));

        self::assertNotNull($item);
        self::assertSame(StorageLocation::FRIDGE, $item->location());
        self::assertSame('5.000000', $item->minimumThreshold()->quantity()->amount());
    }

    public function testAnArticleWithoutAPlaceIsFollowedAnyway(): void
    {
        $id = $this->open($this->catalogue(exists: true, active: true), new OpenStockItem(self::CLINIC_ID, self::ARTICLE_ID, 'TABLET', '0.000000', 'TABLET', 'ABSOLUTE', true));

        self::assertNull($this->items->findById(StockItemId::fromString($id), ClinicId::fromString(self::CLINIC_ID))?->location());
    }

    public function testAnArticleTheCatalogueDoesNotKnowIsRefused(): void
    {
        $this->expectException(ArticleNotFoundException::class);

        $this->open($this->catalogue(exists: false, active: false), new OpenStockItem(self::CLINIC_ID, self::ARTICLE_ID, 'TABLET', '0.000000', 'TABLET', 'ABSOLUTE', true));
    }

    public function testAnArchivedArticleIsRefused(): void
    {
        $this->expectException(ArticleNotActiveException::class);

        $this->open($this->catalogue(exists: true, active: false), new OpenStockItem(self::CLINIC_ID, self::ARTICLE_ID, 'TABLET', '0.000000', 'TABLET', 'ABSOLUTE', true));
    }

    public function testAnArticleIsFollowedOnlyOnce(): void
    {
        $this->stockItem();

        $this->expectException(DuplicateStockItemException::class);

        $this->open($this->catalogue(exists: true, active: true), new OpenStockItem(self::CLINIC_ID, self::ARTICLE_ID, 'TABLET', '0.000000', 'TABLET', 'ABSOLUTE', true));
    }

    public function testAnArticleChangesPlaceOrLosesIt(): void
    {
        $item = $this->stockItem();

        $this->relocate(new RelocateStockItem(self::STOCK_ITEM_ID, self::CLINIC_ID, 'NARCOTICS_SAFE'));
        self::assertSame(StorageLocation::NARCOTICS_SAFE, $item->location());

        $this->relocate(new RelocateStockItem(self::STOCK_ITEM_ID, self::CLINIC_ID, null));
        self::assertNull($item->location());
        self::assertCount(2, $this->announcedOf(StockItemRelocated::class));
    }

    public function testThresholdPlaceArchiveAndRestoreAllNeedAKnownItem(): void
    {
        $refused  = 0;
        $attempts = [
            fn () => $this->relocate(new RelocateStockItem(self::STOCK_ITEM_ID, self::CLINIC_ID, 'FRIDGE')),
            fn () => $this->threshold(new ChangeStockThreshold(self::STOCK_ITEM_ID, self::CLINIC_ID, '1.000000', 'TABLET', 'ABSOLUTE')),
            fn () => $this->archive(new ArchiveStockItem(self::STOCK_ITEM_ID, self::CLINIC_ID)),
            fn () => $this->restore(new RestoreStockItem(self::STOCK_ITEM_ID, self::CLINIC_ID)),
        ];

        foreach ($attempts as $attempt) {
            try {
                $attempt();
            } catch (StockItemNotFoundException) {
                ++$refused;
            }
        }

        self::assertSame(4, $refused);
    }

    public function testTheThresholdIsWhatTheClinicSets(): void
    {
        $item = $this->stockItem();

        $this->threshold(new ChangeStockThreshold(self::STOCK_ITEM_ID, self::CLINIC_ID, '24.000000', 'TABLET', 'ABSOLUTE'));

        self::assertSame('24.000000', $item->minimumThreshold()->quantity()->amount());
        self::assertCount(1, $this->announcedOf(StockThresholdChanged::class));
    }

    public function testAnEmptyShelfStopsBeingFollowedThenComesBack(): void
    {
        $item = $this->stockItem();

        $this->archive(new ArchiveStockItem(self::STOCK_ITEM_ID, self::CLINIC_ID));
        self::assertSame(StockItemStatus::ARCHIVED, $item->status());

        $this->restore(new RestoreStockItem(self::STOCK_ITEM_ID, self::CLINIC_ID));
        self::assertSame(StockItemStatus::ACTIVE, $item->status());
        self::assertCount(1, $this->announcedOf(StockItemArchived::class));
        self::assertCount(1, $this->announcedOf(StockItemRestored::class));
    }

    private function catalogue(bool $exists, bool $active): ArticleProviderInterface
    {
        $catalogue = $this->createStub(ArticleProviderInterface::class);
        $catalogue->method('exists')->willReturn($exists);
        $catalogue->method('isActive')->willReturn($active);

        return $catalogue;
    }

    private function open(ArticleProviderInterface $catalogue, OpenStockItem $command): string
    {
        return new OpenStockItemHandler($this->items, $catalogue, $this->publisher(), $this->clock())($command);
    }

    private function relocate(RelocateStockItem $command): void
    {
        new RelocateStockItemHandler($this->items, $this->publisher(), $this->clock())($command);
    }

    private function threshold(ChangeStockThreshold $command): void
    {
        new ChangeStockThresholdHandler($this->items, $this->publisher(), $this->clock())($command);
    }

    private function archive(ArchiveStockItem $command): void
    {
        new ArchiveStockItemHandler($this->items, $this->publisher(), $this->clock())($command);
    }

    private function restore(RestoreStockItem $command): void
    {
        new RestoreStockItemHandler($this->items, $this->publisher(), $this->clock())($command);
    }
}
