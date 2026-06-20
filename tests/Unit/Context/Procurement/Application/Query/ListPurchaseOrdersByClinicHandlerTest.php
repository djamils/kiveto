<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Query;

use App\Context\Procurement\Application\Port\PurchaseOrderReadRepositoryInterface;
use App\Context\Procurement\Application\Query\ListPurchaseOrdersByClinic\ListPurchaseOrdersByClinic;
use App\Context\Procurement\Application\Query\ListPurchaseOrdersByClinic\ListPurchaseOrdersByClinicHandler;
use PHPUnit\Framework\TestCase;

final class ListPurchaseOrdersByClinicHandlerTest extends TestCase
{
    private const string CLINIC_UUID = '01932b00-0000-7000-8000-000000000010';

    public function testItReturnsPurchaseOrdersForClinic(): void
    {
        $readRepository = $this->createStub(PurchaseOrderReadRepositoryInterface::class);
        $readRepository->method('findByClinic')->with(self::CLINIC_UUID)
            ->willReturn([['id' => 'po-1', 'status' => 'DRAFT']])
        ;

        $handler = new ListPurchaseOrdersByClinicHandler($readRepository);
        $result  = $handler(new ListPurchaseOrdersByClinic(self::CLINIC_UUID));

        self::assertCount(1, $result);
        self::assertSame('DRAFT', $result[0]['status']);
    }
}
