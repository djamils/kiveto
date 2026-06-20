<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Exception;

use App\Context\Procurement\Application\Exception\CatalogImportNotSupportedException;
use App\Context\Procurement\Domain\PurchaseOrder\Exception\ConcurrentModificationException;
use App\Context\Procurement\Domain\PurchaseOrder\Exception\PurchaseOrderClosedException;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderTotals;
use App\Context\Procurement\Domain\SupplierReceipt\Exception\SupplierReceiptNotFoundException;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class ProcurementExceptionsTest extends TestCase
{
    public function testCatalogImportNotSupportedExceptionMessage(): void
    {
        $exception = new CatalogImportNotSupportedException('centravet-csv');

        self::assertStringContainsString('centravet-csv', $exception->getMessage());
        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testConcurrentModificationExceptionMessage(): void
    {
        $exception = new ConcurrentModificationException('po-id-42');

        self::assertStringContainsString('po-id-42', $exception->getMessage());
        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testPurchaseOrderClosedExceptionMessage(): void
    {
        $exception = new PurchaseOrderClosedException('po-id-123');

        self::assertStringContainsString('po-id-123', $exception->getMessage());
        self::assertInstanceOf(\LogicException::class, $exception);
    }

    public function testSupplierReceiptNotFoundExceptionMessage(): void
    {
        $exception = new SupplierReceiptNotFoundException('receipt-id-7');

        self::assertStringContainsString('receipt-id-7', $exception->getMessage());
        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testPurchaseOrderTotalsExposesSubtotal(): void
    {
        $money  = Money::fromMinorUnits(12345, CurrencyCode::fromString('EUR'));
        $totals = new PurchaseOrderTotals($money);

        self::assertSame($money, $totals->subtotal);
    }
}
