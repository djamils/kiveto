<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Application\Query\StockOverview;

use App\Context\Inventory\Application\Port\StockOverviewReadRepositoryInterface;
use App\Context\Inventory\Application\Query\StockOverview\ListArticleIdsInState;
use App\Context\Inventory\Application\Query\StockOverview\ListArticleIdsInStateHandler;
use App\Context\Inventory\Application\Query\StockOverview\ListLotsForStockItems;
use App\Context\Inventory\Application\Query\StockOverview\ListLotsForStockItemsHandler;
use App\Context\Inventory\Application\Query\StockOverview\ListMovements;
use App\Context\Inventory\Application\Query\StockOverview\ListMovementsHandler;
use App\Context\Inventory\Application\Query\StockOverview\LotRow;
use App\Context\Inventory\Application\Query\StockOverview\MovementCriteria;
use App\Context\Inventory\Application\Query\StockOverview\MovementRow;
use App\Context\Inventory\Application\Query\StockOverview\StockSnapshotForArticles;
use App\Context\Inventory\Application\Query\StockOverview\StockSnapshotForArticlesHandler;
use App\Context\Inventory\Application\Query\StockOverview\StockSnapshotRow;
use App\Context\Inventory\Application\Query\StockOverview\StockStateFilter;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class StockOverviewHandlersTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-000000000001';

    public function testTheSnapshotIsAskedOfTheRepositoryForTheGivenArticles(): void
    {
        $row  = $this->snapshotRow();
        $repo = $this->createMock(StockOverviewReadRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('snapshotFor')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC === $id->toString()),
                ['article-1'],
            )
            ->willReturn(['article-1' => $row])
        ;

        $handler = new StockSnapshotForArticlesHandler($repo);

        self::assertSame(['article-1' => $row], $handler(new StockSnapshotForArticles(self::CLINIC, ['article-1'])));
    }

    public function testAnEmptyArticleListNeverReachesTheDatabase(): void
    {
        $repo = $this->createMock(StockOverviewReadRepositoryInterface::class);
        $repo->expects(self::never())->method('snapshotFor');

        $handler = new StockSnapshotForArticlesHandler($repo);

        self::assertSame([], $handler(new StockSnapshotForArticles(self::CLINIC, [])));
    }

    public function testTheStateFilterPassesItsExpiryWindowThrough(): void
    {
        $repo = $this->createMock(StockOverviewReadRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('articleIdsInState')
            ->with(self::anything(), StockStateFilter::EXPIRING, 15)
            ->willReturn(['article-1', 'article-2'])
        ;

        $handler = new ListArticleIdsInStateHandler($repo);

        self::assertSame(
            ['article-1', 'article-2'],
            $handler(new ListArticleIdsInState(self::CLINIC, StockStateFilter::EXPIRING, 15)),
        );
    }

    public function testTheMovementsAreWrappedInTheirPage(): void
    {
        $movement = $this->movementRow();
        $repo     = $this->createMock(StockOverviewReadRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('movements')
            ->willReturn(['items' => [$movement], 'total' => 42])
        ;

        $handler = new ListMovementsHandler($repo);
        $page    = $handler(new ListMovements(self::CLINIC, new MovementCriteria(page: 2, perPage: 25)));

        self::assertSame([$movement], $page->items);
        self::assertSame(42, $page->total);
        self::assertSame(2, $page->page);
        self::assertSame(25, $page->perPage);
        self::assertSame(2, $page->pageCount());
    }

    public function testTheLotsOfSeveralItemsAreReadInOneGo(): void
    {
        $lot  = $this->lotRow();
        $repo = $this->createMock(StockOverviewReadRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('lotsFor')
            ->with(self::anything(), ['item-1'])
            ->willReturn(['item-1' => [$lot]])
        ;

        $handler = new ListLotsForStockItemsHandler($repo);

        self::assertSame(['item-1' => [$lot]], $handler(new ListLotsForStockItems(self::CLINIC, ['item-1'])));
    }

    public function testAskingForTheLotsOfNothingReadsNothing(): void
    {
        $repo = $this->createMock(StockOverviewReadRepositoryInterface::class);
        $repo->expects(self::never())->method('lotsFor');

        $handler = new ListLotsForStockItemsHandler($repo);

        self::assertSame([], $handler(new ListLotsForStockItems(self::CLINIC, [])));
    }

    private function snapshotRow(): StockSnapshotRow
    {
        return new StockSnapshotRow(
            stockItemId: 'item-1',
            articleId: 'article-1',
            onHand: '10.000000',
            reserved: '0.000000',
            available: '10.000000',
            threshold: '2.000000',
            unit: 'UNIT',
            thresholdType: 'ABSOLUTE',
            trackStock: true,
            status: 'ACTIVE',
            activeLotCount: 1,
            earliestExpiry: '2027-01-01',
            expiredLotCount: 0,
            recalledLotCount: 0,
        );
    }

    private function movementRow(): MovementRow
    {
        return new MovementRow(
            id: 'movement-1',
            articleId: 'article-1',
            lotId: null,
            lotNumber: null,
            type: 'IN',
            reason: 'RECEIPT_OPENING',
            quantity: '10.000000',
            unit: 'UNIT',
            occurredAt: new \DateTimeImmutable('2026-08-22 10:00:00'),
            reference: null,
            performedBy: null,
            note: null,
        );
    }

    private function lotRow(): LotRow
    {
        return new LotRow(
            id: 'lot-1',
            stockItemId: 'item-1',
            number: 'LOT-1',
            quantity: '10.000000',
            unit: 'UNIT',
            expiryDate: '2027-01-01',
            status: 'ACTIVE',
        );
    }
}
