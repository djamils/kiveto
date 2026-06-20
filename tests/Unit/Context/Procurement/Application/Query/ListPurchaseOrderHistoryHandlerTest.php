<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Query;

use App\Context\Procurement\Application\Port\PurchaseOrderReadRepositoryInterface;
use App\Context\Procurement\Application\Query\ListPurchaseOrderHistory\ListPurchaseOrderHistory;
use App\Context\Procurement\Application\Query\ListPurchaseOrderHistory\ListPurchaseOrderHistoryHandler;
use PHPUnit\Framework\TestCase;

final class ListPurchaseOrderHistoryHandlerTest extends TestCase
{
    private const string CLINIC_UUID = '01932b00-0000-7000-8000-000000000010';

    public function testItReturnsPurchaseOrderHistory(): void
    {
        $readRepository = $this->createStub(PurchaseOrderReadRepositoryInterface::class);
        $readRepository->method('findByClinic')->with(self::CLINIC_UUID)
            ->willReturn([
                ['id' => 'po-1', 'status' => 'CLOSED'],
                ['id' => 'po-2', 'status' => 'CANCELLED'],
            ])
        ;

        $handler = new ListPurchaseOrderHistoryHandler($readRepository);
        $result  = $handler(new ListPurchaseOrderHistory(self::CLINIC_UUID));

        self::assertCount(2, $result);
    }
}
