<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Query;

use App\Context\Procurement\Application\Port\PurchaseOrderReadRepositoryInterface;
use App\Context\Procurement\Application\Query\GetStaleOpenPurchaseOrders\GetStaleOpenPurchaseOrders;
use App\Context\Procurement\Application\Query\GetStaleOpenPurchaseOrders\GetStaleOpenPurchaseOrdersHandler;
use PHPUnit\Framework\TestCase;

final class GetStaleOpenPurchaseOrdersHandlerTest extends TestCase
{
    public function testItReturnsStaleOrders(): void
    {
        $readRepository = $this->createStub(PurchaseOrderReadRepositoryInterface::class);
        $readRepository->method('findStale')->with(60)
            ->willReturn([['id' => 'po-old', 'status' => 'PARTIALLY_RECEIVED']])
        ;

        $handler = new GetStaleOpenPurchaseOrdersHandler($readRepository);
        $result  = $handler(new GetStaleOpenPurchaseOrders(daysThreshold: 60));

        self::assertCount(1, $result);
        self::assertSame('po-old', $result[0]['id']);
    }

    public function testItUsesDefaultThreshold(): void
    {
        $readRepository = $this->createMock(PurchaseOrderReadRepositoryInterface::class);
        $readRepository->expects(self::once())->method('findStale')->with(60)->willReturn([]);

        $handler = new GetStaleOpenPurchaseOrdersHandler($readRepository);
        $result  = $handler(new GetStaleOpenPurchaseOrders());

        self::assertSame([], $result);
    }
}
