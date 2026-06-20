<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Infrastructure\Adapter\Supplier\Manual;

use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\ExternalReference;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderNumber;
use App\Context\Procurement\Domain\Shared\ValueObject\Address;
use App\Context\Procurement\Domain\Shared\ValueObject\ArticleId;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierIntegrationMode;
use App\Context\Procurement\Domain\SupplierAccount\SupplierAccount;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\CustomerCode;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Infrastructure\Adapter\Supplier\Manual\AlcyonCsvExporter;
use App\Context\Procurement\Infrastructure\Adapter\Supplier\Manual\CentravetCsvExporter;
use App\Context\Procurement\Infrastructure\Adapter\Supplier\Manual\GenericCsvExporter;
use App\Shared\Domain\Storage\FileStorageInterface;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class CsvExportersTest extends TestCase
{
    private const string PO_UUID       = '01932b00-0000-7000-8000-000000000100';
    private const string LINE1_UUID    = '01932b00-0000-7000-8000-000000000101';
    private const string LINE2_UUID    = '01932b00-0000-7000-8000-000000000102';
    private const string CLINIC_UUID   = '01932b00-0000-7000-8000-000000000003';
    private const string SUPPLIER_UUID = '01932b00-0000-7000-8000-000000000001';
    private const string ACCOUNT_UUID  = '01932b00-0000-7000-8000-000000000002';
    private const string ARTICLE_UUID  = '01932b00-0000-7000-8000-000000000200';
    private const string CATALOG_UUID  = '01932b00-0000-7000-8000-000000000300';

    public function testCentravetExporterIdentifierAndFilename(): void
    {
        $storage  = $this->makeStorage();
        $exporter = new CentravetCsvExporter($storage);

        self::assertSame('centravet_csv', $exporter->identifier());
        self::assertSame(SupplierIntegrationMode::MANUAL_EXPORT, $exporter->mode());
        self::assertFalse($exporter->supportsAsyncDeliveryPolling());
        self::assertFalse($exporter->supportsCatalogImport());
        self::assertSame([], $exporter->fetchCatalog($this->makeStubSupplier()));
        self::assertSame([], $exporter->fetchDeliveries(
            $this->makeStubSupplier(),
            $this->makeAccount(),
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
        ));
    }

    public function testCentravetSendOrderProducesCsvFileWithSkippedCancelledLine(): void
    {
        $capturedContent = null;
        $storage         = $this->createStub(FileStorageInterface::class);
        $storage->method('store')->willReturnCallback(
            static function (string $filename, string $content) use (&$capturedContent): string {
                self::assertStringContainsString('centravet-order-', $filename);
                $capturedContent = $content;

                return 'file-id-1';
            },
        );
        $storage->method('getUrl')->willReturn('https://files.example/file-id-1');

        $exporter = new CentravetCsvExporter($storage);
        $order    = $this->makePurchaseOrderWithCancelledLine();
        $result   = $exporter->sendOrder($order, $this->makeAccount());

        self::assertTrue($result->success);
        self::assertInstanceOf(ExternalReference::class, $result->externalReference);
        self::assertSame('https://files.example/file-id-1', $result->trackingUrl);
        self::assertIsString($capturedContent);
        self::assertStringContainsString('CODE_FOURNISSEUR', $capturedContent);
        // Active line included; cancelled line skipped
        self::assertStringContainsString(self::CATALOG_UUID, $capturedContent);
        self::assertSame(1, substr_count($capturedContent, self::CATALOG_UUID));
    }

    public function testAlcyonExporterIdentifierAndOutput(): void
    {
        $capturedContent = null;
        $storage         = $this->createStub(FileStorageInterface::class);
        $storage->method('store')->willReturnCallback(
            static function (string $filename, string $content) use (&$capturedContent): string {
                self::assertStringContainsString('alcyon-order-', $filename);
                $capturedContent = $content;

                return 'file-id-2';
            },
        );
        $storage->method('getUrl')->willReturn('https://files.example/file-id-2');

        $exporter = new AlcyonCsvExporter($storage);
        self::assertSame('alcyon_csv', $exporter->identifier());

        $result = $exporter->sendOrder($this->makePurchaseOrderWithCancelledLine(), $this->makeAccount());

        self::assertTrue($result->success);
        self::assertIsString($capturedContent);
        self::assertStringContainsString('REFERENCE_COMMANDE', $capturedContent);
        // Account customer code present in Alcyon CSV
        self::assertStringContainsString('CVT-CUST-001', $capturedContent);
    }

    public function testGenericExporterIdentifierAndOutput(): void
    {
        $capturedContent = null;
        $storage         = $this->createStub(FileStorageInterface::class);
        $storage->method('store')->willReturnCallback(
            static function (string $filename, string $content) use (&$capturedContent): string {
                self::assertStringContainsString('generic-order-', $filename);
                $capturedContent = $content;

                return 'file-id-3';
            },
        );
        $storage->method('getUrl')->willReturn('https://files.example/file-id-3');

        $exporter = new GenericCsvExporter($storage);
        self::assertSame('generic_csv', $exporter->identifier());

        $result = $exporter->sendOrder($this->makePurchaseOrderWithCancelledLine(), $this->makeAccount());

        self::assertTrue($result->success);
        self::assertIsString($capturedContent);
        self::assertStringContainsString('supplier_product_code', $capturedContent);
        self::assertStringContainsString('EUR', $capturedContent);
    }

    private function makeStorage(): FileStorageInterface
    {
        $storage = $this->createStub(FileStorageInterface::class);
        $storage->method('store')->willReturn('file-id');
        $storage->method('getUrl')->willReturn('https://files.example/file-id');

        return $storage;
    }

    private function makePurchaseOrderWithCancelledLine(): PurchaseOrder
    {
        $po = PurchaseOrder::create(
            id: PurchaseOrderId::fromString(self::PO_UUID),
            clinicId: ClinicId::fromString(self::CLINIC_UUID),
            supplierId: SupplierId::fromString(self::SUPPLIER_UUID),
            supplierAccountId: SupplierAccountId::fromString(self::ACCOUNT_UUID),
            orderNumber: PurchaseOrderNumber::fromString('PO-2026-000010'),
            currency: CurrencyCode::fromString('EUR'),
            deliveryAddress: Address::create(null, null, null, null, null),
        );
        $_ = $po->pullDomainEvents();

        $line1Id = PurchaseOrderLineId::fromString(self::LINE1_UUID);
        $po->addLine(
            lineId: $line1Id,
            articleId: ArticleId::fromString(self::ARTICLE_UUID),
            catalogEntryId: SupplierCatalogEntryId::fromString(self::CATALOG_UUID),
            orderedAmount: '5',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: Money::fromMinorUnits(1000, CurrencyCode::fromString('EUR')),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );
        $line2Id = PurchaseOrderLineId::fromString(self::LINE2_UUID);
        $po->addLine(
            lineId: $line2Id,
            articleId: ArticleId::fromString(self::ARTICLE_UUID),
            catalogEntryId: SupplierCatalogEntryId::fromString('01932b00-0000-7000-8000-000000000301'),
            orderedAmount: '3',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: Money::fromMinorUnits(500, CurrencyCode::fromString('EUR')),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );
        $po->markAsSubmitting(new \DateTimeImmutable());
        $po->submit(ExternalReference::create('EXT-001', 'test'), new \DateTimeImmutable(), new \DateTimeImmutable());
        $po->cancelLine($line2Id, 'out of stock', new \DateTimeImmutable());
        $_ = $po->pullDomainEvents();

        return $po;
    }

    private function makeAccount(): SupplierAccount
    {
        return SupplierAccount::create(
            id: SupplierAccountId::fromString(self::ACCOUNT_UUID),
            clinicId: ClinicId::fromString(self::CLINIC_UUID),
            supplierId: SupplierId::fromString(self::SUPPLIER_UUID),
            customerCode: CustomerCode::fromString('CVT-CUST-001'),
        );
    }

    private function makeStubSupplier(): \App\Context\Procurement\Domain\Supplier\Supplier
    {
        return \App\Context\Procurement\Domain\Supplier\Supplier::register(
            id: SupplierId::fromString(self::SUPPLIER_UUID),
            name: \App\Context\Procurement\Domain\Supplier\ValueObject\SupplierName::fromString('S'),
            code: \App\Context\Procurement\Domain\Supplier\ValueObject\SupplierCode::fromString('SUP'),
            type: \App\Context\Procurement\Domain\Supplier\ValueObject\SupplierType::CENTRALE,
            countryCode: \App\Shared\Domain\ValueObject\CountryCode::fromString('FR'),
            defaultCurrency: CurrencyCode::fromString('EUR'),
            integrationMode: SupplierIntegrationMode::MANUAL_EXPORT,
            adapterIdentifier: null,
        );
    }
}
