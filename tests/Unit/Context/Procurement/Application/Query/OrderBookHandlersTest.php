<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Query;

use App\Context\Procurement\Application\Port\OrderBookReadRepositoryInterface;
use App\Context\Procurement\Application\Query\OrderBook\ListOpenPurchaseOrders;
use App\Context\Procurement\Application\Query\OrderBook\ListOpenPurchaseOrdersHandler;
use App\Context\Procurement\Application\Query\OrderBook\ListSupplyOffers;
use App\Context\Procurement\Application\Query\OrderBook\ListSupplyOffersHandler;
use App\Context\Procurement\Application\Query\OrderBook\OpenPurchaseOrder;
use App\Context\Procurement\Application\Query\OrderBook\OpenPurchaseOrderLine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListSupplyOffersHandler::class)]
#[CoversClass(ListSupplyOffers::class)]
#[CoversClass(ListOpenPurchaseOrdersHandler::class)]
#[CoversClass(ListOpenPurchaseOrders::class)]
#[CoversClass(OpenPurchaseOrderLine::class)]
final class OrderBookHandlersTest extends TestCase
{
    private const string CLINIC = '01970005-0000-7000-8000-000000000001';

    public function testTheOffersAreAskedOnceForEachBarcodeAndReference(): void
    {
        $repo = $this->createMock(OrderBookReadRepositoryInterface::class);
        $repo->expects(self::once())->method('supplyOffers')->with(self::CLINIC, ['0366'], ['APOQUEL', 'CATH'])->willReturn([]);

        self::assertSame([], new ListSupplyOffersHandler($repo)(new ListSupplyOffers(self::CLINIC, ['0366', '', '0366'], ['APOQUEL', 'CATH', 'APOQUEL', ''])));
    }

    public function testNothingToMatchReadsNothing(): void
    {
        $repo = $this->createMock(OrderBookReadRepositoryInterface::class);
        $repo->expects(self::never())->method('supplyOffers');

        self::assertSame([], new ListSupplyOffersHandler($repo)(new ListSupplyOffers(self::CLINIC, [''], [])));
    }

    public function testTheOpenOrdersAreTheRepositorysOwn(): void
    {
        $order = new OpenPurchaseOrder('po', 'PO-2026-000001', 'sup', 'Alcyon', 'acc', 'SUBMITTED', 'EUR', '2026-08-19 16:40:00', '2026-08-19 16:40:00', [
            new OpenPurchaseOrderLine('line', 'art', 'entry', '50', 'UNIT', '0', 95, 'ACTIVE'),
        ]);

        $repo = $this->createStub(OrderBookReadRepositoryInterface::class);
        $repo->method('openOrders')->willReturn([$order]);

        self::assertSame([$order], new ListOpenPurchaseOrdersHandler($repo)(new ListOpenPurchaseOrders(self::CLINIC)));
    }
}
