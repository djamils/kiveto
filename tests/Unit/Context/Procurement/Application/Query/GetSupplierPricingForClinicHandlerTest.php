<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Query;

use App\Context\Procurement\Application\Port\SupplierPricingReadRepositoryInterface;
use App\Context\Procurement\Application\Query\GetSupplierPricingForClinic\GetSupplierPricingForClinic;
use App\Context\Procurement\Application\Query\GetSupplierPricingForClinic\GetSupplierPricingForClinicHandler;
use PHPUnit\Framework\TestCase;

final class GetSupplierPricingForClinicHandlerTest extends TestCase
{
    private const string CLINIC_UUID   = '01932b00-0000-7000-8000-000000000010';
    private const string SUPPLIER_UUID = '01932b00-0000-7000-8000-000000000001';

    public function testItReturnsPricingForClinic(): void
    {
        $readRepository = $this->createStub(SupplierPricingReadRepositoryInterface::class);
        $readRepository->method('findByClinic')->with(self::CLINIC_UUID)
            ->willReturn([['id' => 'pricing-1', 'amountMinor' => 1200]])
        ;

        $handler = new GetSupplierPricingForClinicHandler($readRepository);
        $result  = $handler(new GetSupplierPricingForClinic(
            clinicId: self::CLINIC_UUID,
            supplierId: self::SUPPLIER_UUID,
        ));

        self::assertCount(1, $result);
        self::assertSame(1200, $result[0]['amountMinor']);
    }
}
