<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Query;

use App\Context\Procurement\Application\Port\PurchaseOrderReadRepositoryInterface;
use App\Context\Procurement\Application\Query\GetPurchaseOrderDetail\GetPurchaseOrderDetail;
use App\Context\Procurement\Application\Query\GetPurchaseOrderDetail\GetPurchaseOrderDetailHandler;
use App\Context\Procurement\Domain\PurchaseOrder\Exception\PurchaseOrderNotFoundException;
use PHPUnit\Framework\TestCase;

final class GetPurchaseOrderDetailHandlerTest extends TestCase
{
    private const string PO_UUID     = '01932b00-0000-7000-8000-000000000005';
    private const string CLINIC_UUID = '01932b00-0000-7000-8000-000000000010';

    public function testItReturnsPurchaseOrderDetail(): void
    {
        $readRepository = $this->createStub(PurchaseOrderReadRepositoryInterface::class);
        $readRepository->method('findById')->with(self::PO_UUID)
            ->willReturn(['id' => self::PO_UUID, 'status' => 'SUBMITTED'])
        ;

        $handler = new GetPurchaseOrderDetailHandler($readRepository);
        $result  = $handler(new GetPurchaseOrderDetail(
            purchaseOrderId: self::PO_UUID,
            clinicId: self::CLINIC_UUID,
        ));

        self::assertSame(self::PO_UUID, $result['id']);
    }

    public function testItThrowsWhenNotFound(): void
    {
        $readRepository = $this->createStub(PurchaseOrderReadRepositoryInterface::class);
        $readRepository->method('findById')->willReturn(null);

        $handler = new GetPurchaseOrderDetailHandler($readRepository);

        $this->expectException(PurchaseOrderNotFoundException::class);
        $handler(new GetPurchaseOrderDetail(
            purchaseOrderId: self::PO_UUID,
            clinicId: self::CLINIC_UUID,
        ));
    }
}
