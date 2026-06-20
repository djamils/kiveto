<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Service;

use App\Context\Procurement\Application\Service\PurchaseOrderTotalsCalculator;
use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderNumber;
use App\Context\Procurement\Domain\Shared\ValueObject\Address;
use App\Context\Procurement\Domain\Shared\ValueObject\ArticleId;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class PurchaseOrderTotalsCalculatorTest extends TestCase
{
    public function testCalculateSubtotalReturnsOrderTotal(): void
    {
        $po = PurchaseOrder::create(
            id: PurchaseOrderId::fromString('01932b00-0000-7000-8000-000000000100'),
            clinicId: ClinicId::fromString('01932b00-0000-7000-8000-000000000003'),
            supplierId: SupplierId::fromString('01932b00-0000-7000-8000-000000000001'),
            supplierAccountId: SupplierAccountId::fromString('01932b00-0000-7000-8000-000000000002'),
            orderNumber: PurchaseOrderNumber::fromString('PO-2026-000001'),
            currency: CurrencyCode::fromString('EUR'),
            deliveryAddress: Address::create(null, null, null, null, null),
            createdAt: new \DateTimeImmutable('2026-01-01'),
        );

        $po->addLine(
            lineId: PurchaseOrderLineId::fromString('01932b00-0000-7000-8000-000000000101'),
            articleId: ArticleId::fromString('01932b00-0000-7000-8000-000000000200'),
            catalogEntryId: SupplierCatalogEntryId::fromString('01932b00-0000-7000-8000-000000000300'),
            orderedAmount: '5',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: Money::fromMinorUnits(200, CurrencyCode::fromString('EUR')),
            note: null,
            updatedAt: new \DateTimeImmutable('2026-01-01'),
        );

        $calculator = new PurchaseOrderTotalsCalculator();
        $subtotal   = $calculator->calculateSubtotal($po);

        // 5 * 200 = 1000 minor units
        self::assertSame(1000, $subtotal->minorUnits());
    }
}
