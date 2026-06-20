<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Domain\ValueObjects;

use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\ExternalReference;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierName;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\CustomerCode;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductCode;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductName;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\SupplierPricingId;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\LotInformation;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptId;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptLineId;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    private const string UUID_A = '01932b00-0000-7000-8000-00000000000a';
    private const string UUID_B = '01932b00-0000-7000-8000-00000000000b';

    public function testPurchaseOrderIdEquals(): void
    {
        $a = PurchaseOrderId::fromString(self::UUID_A);
        $b = PurchaseOrderId::fromString(self::UUID_A);
        $c = PurchaseOrderId::fromString(self::UUID_B);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    public function testPurchaseOrderIdRejectsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PurchaseOrderId::fromString('not-a-uuid');
    }

    public function testPurchaseOrderLineIdEquals(): void
    {
        $a = PurchaseOrderLineId::fromString(self::UUID_A);
        $b = PurchaseOrderLineId::fromString(self::UUID_A);

        self::assertTrue($a->equals($b));
    }

    public function testPurchaseOrderLineIdRejectsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PurchaseOrderLineId::fromString('bad');
    }

    public function testSupplierIdEquals(): void
    {
        $a = SupplierId::fromString(self::UUID_A);
        $b = SupplierId::fromString(self::UUID_A);

        self::assertTrue($a->equals($b));
    }

    public function testSupplierIdRejectsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SupplierId::fromString('not-uuid');
    }

    public function testSupplierAccountIdEquals(): void
    {
        $a = SupplierAccountId::fromString(self::UUID_A);
        $b = SupplierAccountId::fromString(self::UUID_A);

        self::assertTrue($a->equals($b));
    }

    public function testSupplierAccountIdRejectsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SupplierAccountId::fromString('bad');
    }

    public function testSupplierCatalogEntryIdEquals(): void
    {
        $a = SupplierCatalogEntryId::fromString(self::UUID_A);
        $b = SupplierCatalogEntryId::fromString(self::UUID_A);

        self::assertTrue($a->equals($b));
    }

    public function testSupplierCatalogEntryIdRejectsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SupplierCatalogEntryId::fromString('bad');
    }

    public function testSupplierPricingIdEquals(): void
    {
        $a = SupplierPricingId::fromString(self::UUID_A);
        $b = SupplierPricingId::fromString(self::UUID_A);

        self::assertTrue($a->equals($b));
    }

    public function testSupplierPricingIdRejectsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SupplierPricingId::fromString('bad');
    }

    public function testSupplierReceiptIdEquals(): void
    {
        $a = SupplierReceiptId::fromString(self::UUID_A);
        $b = SupplierReceiptId::fromString(self::UUID_A);

        self::assertTrue($a->equals($b));
    }

    public function testSupplierReceiptIdRejectsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SupplierReceiptId::fromString('bad');
    }

    public function testSupplierReceiptLineIdEquals(): void
    {
        $a = SupplierReceiptLineId::fromString(self::UUID_A);
        $b = SupplierReceiptLineId::fromString(self::UUID_A);

        self::assertTrue($a->equals($b));
    }

    public function testSupplierReceiptLineIdRejectsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SupplierReceiptLineId::fromString('bad');
    }

    public function testSupplierNameRejectsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SupplierName cannot be empty.');
        SupplierName::fromString('   ');
    }

    public function testSupplierNameRejectsTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SupplierName must not exceed 255 characters.');
        SupplierName::fromString(str_repeat('a', 256));
    }

    public function testSupplierProductCodeRejectsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SupplierProductCode cannot be empty.');
        SupplierProductCode::fromString('   ');
    }

    public function testSupplierProductCodeRejectsTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SupplierProductCode must not exceed 128 characters.');
        SupplierProductCode::fromString(str_repeat('a', 129));
    }

    public function testSupplierProductNameRejectsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SupplierProductName cannot be empty.');
        SupplierProductName::fromString('   ');
    }

    public function testSupplierProductNameRejectsTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SupplierProductName must not exceed 255 characters.');
        SupplierProductName::fromString(str_repeat('a', 256));
    }

    public function testCustomerCodeRejectsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CustomerCode cannot be empty.');
        CustomerCode::fromString('   ');
    }

    public function testCustomerCodeRejectsTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CustomerCode must not exceed 64 characters.');
        CustomerCode::fromString(str_repeat('a', 65));
    }

    public function testLotInformationRejectsEmptyLotNumber(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Lot number cannot be empty.');
        LotInformation::create('   ', new \DateTimeImmutable('2026-12-31'));
    }

    public function testLotInformationRejectsTooLongLotNumber(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Lot number must not exceed 64 characters.');
        LotInformation::create(str_repeat('a', 65), new \DateTimeImmutable('2026-12-31'));
    }

    public function testExternalReferenceRejectsEmptyValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ExternalReference value cannot be empty.');
        ExternalReference::create('   ', 'centravet');
    }

    public function testExternalReferenceRejectsEmptyProvidedBy(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ExternalReference providedBy cannot be empty.');
        ExternalReference::create('EXT-42', '   ');
    }

    public function testExternalReferenceToString(): void
    {
        $ref = ExternalReference::create('EXT-42', 'centravet');

        self::assertSame('EXT-42', $ref->toString());
        self::assertSame('EXT-42', $ref->value);
        self::assertSame('centravet', $ref->providedBy);
    }
}
