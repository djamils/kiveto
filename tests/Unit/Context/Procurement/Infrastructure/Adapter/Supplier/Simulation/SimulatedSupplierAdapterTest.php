<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Infrastructure\Adapter\Supplier\Simulation;

use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderNumber;
use App\Context\Procurement\Domain\Shared\ValueObject\Address;
use App\Context\Procurement\Domain\Shared\ValueObject\ArticleId;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\Supplier;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierCode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierIntegrationMode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierName;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierType;
use App\Context\Procurement\Domain\SupplierAccount\SupplierAccount;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\CustomerCode;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Infrastructure\Adapter\Supplier\Simulation\SimulatedSupplierAdapter;
use App\Context\Procurement\Infrastructure\Adapter\Supplier\Simulation\SimulationProfileConfig;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class SimulatedSupplierAdapterTest extends TestCase
{
    private const string PO_UUID       = '01932b00-0000-7000-8000-000000000100';
    private const string LINE_UUID     = '01932b00-0000-7000-8000-000000000101';
    private const string CLINIC_UUID   = '01932b00-0000-7000-8000-000000000003';
    private const string SUPPLIER_UUID = '01932b00-0000-7000-8000-000000000001';
    private const string ACCOUNT_UUID  = '01932b00-0000-7000-8000-000000000002';
    private const string ARTICLE_UUID  = '01932b00-0000-7000-8000-000000000200';
    private const string CATALOG_UUID  = '01932b00-0000-7000-8000-000000000300';

    private string $tmpProjectDir;

    protected function setUp(): void
    {
        $this->tmpProjectDir = sys_get_temp_dir() . '/sim-adapter-' . uniqid('', true);
        mkdir($this->tmpProjectDir . '/resources/simulated-catalogs', 0o775, true);
    }

    protected function tearDown(): void
    {
        // Clean up tmp dir
        if (is_dir($this->tmpProjectDir)) {
            $files = glob($this->tmpProjectDir . '/resources/simulated-catalogs/*.yaml') ?: [];
            foreach ($files as $file) {
                unlink($file);
            }
            @rmdir($this->tmpProjectDir . '/resources/simulated-catalogs');
            @rmdir($this->tmpProjectDir . '/resources');
            @rmdir($this->tmpProjectDir);
        }
    }

    public function testIdentifierAndMode(): void
    {
        $adapter = new SimulatedSupplierAdapter(
            $this->createStub(Connection::class),
            $this->tmpProjectDir,
        );

        self::assertSame('simulated', $adapter->identifier());
        self::assertSame(SupplierIntegrationMode::SIMULATION, $adapter->mode());
        self::assertTrue($adapter->supportsAsyncDeliveryPolling());
        self::assertTrue($adapter->supportsCatalogImport());
    }

    public function testSendOrderWithDemoFastInsertsOrderAndImmediateDelivery(): void
    {
        $executeCalls = [];
        $connection   = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $params) use (&$executeCalls): int {
                $executeCalls[] = ['sql' => $sql, 'params' => $params];

                return 1;
            },
        );

        $adapter = new SimulatedSupplierAdapter(
            $connection,
            $this->tmpProjectDir,
            SimulationProfileConfig::DEMO_FAST,
        );

        $result = $adapter->sendOrder($this->makeOrderWithLines(), $this->makeAccount());

        self::assertTrue($result->success);
        self::assertNotNull($result->externalReference);
        self::assertStringStartsWith('SIM-', $result->externalReference->value);
        self::assertSame('simulated', $result->externalReference->providedBy);
        self::assertCount(2, $executeCalls); // 1 order insert + 1 delivery insert
        self::assertStringContainsString('procurement__simulated_orders', $executeCalls[0]['sql']);
        self::assertStringContainsString('procurement__simulated_deliveries', $executeCalls[1]['sql']);
    }

    public function testSendOrderWithStagingRealisticUsesDelayedAvailability(): void
    {
        $executeCalls = [];
        $connection   = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $params) use (&$executeCalls): int {
                $executeCalls[] = $params;

                return 1;
            },
        );

        $adapter = new SimulatedSupplierAdapter(
            $connection,
            $this->tmpProjectDir,
            SimulationProfileConfig::STAGING_REALISTIC,
        );

        $result = $adapter->sendOrder($this->makeOrderWithLines(), $this->makeAccount());

        self::assertTrue($result->success);

        // Delivery insert (2nd call) availableAt should differ from "now" by ~3600s
        $orderInsert    = $executeCalls[0];
        $deliveryInsert = $executeCalls[1];
        self::assertSame(3600, $orderInsert['delay']);
        $createdAtStr   = $orderInsert['createdAt'];
        $availableAtStr = $deliveryInsert['availableAt'];
        self::assertIsString($createdAtStr);
        self::assertIsString($availableAtStr);
        $createdAt   = new \DateTimeImmutable($createdAtStr);
        $availableAt = new \DateTimeImmutable($availableAtStr);
        self::assertSame(3600, $availableAt->getTimestamp() - $createdAt->getTimestamp());
    }

    public function testFetchDeliveriesReturnsEmptyWhenNoRows(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $adapter = new SimulatedSupplierAdapter($connection, $this->tmpProjectDir);

        $result = $adapter->fetchDeliveries(
            $this->makeSupplier(),
            $this->makeAccount(),
            new \DateTimeImmutable('-1 day'),
            new \DateTimeImmutable(),
        );

        self::assertSame([], $result);
    }

    public function testFetchDeliveriesReturnsDataAndMarksAsFetched(): void
    {
        $rows = [
            [
                'id'                      => 'delivery-row-1',
                'simulated_order_id'      => 'order-row-1',
                'purchase_order_id'       => 'po-bin',
                'delivery_note_reference' => 'SIM-DNR-001',
                'payload_json'            => json_encode([
                    [
                        'articleCode' => 'art-1',
                        'qty'         => '5',
                        'unit'        => 'UNIT',
                        'lotNumber'   => null,
                        'expiryDate'  => null,
                        'actualPrice' => null,
                    ],
                ], \JSON_THROW_ON_ERROR),
                'available_at' => '2026-01-15 10:00:00',
                'fetched_at'   => null,
            ],
        ];

        $executeCalls = [];
        $connection   = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($rows);
        $connection->method('fetchAssociative')->willReturn(['external_reference' => 'SIM-EXT-42']);
        $connection->method('executeStatement')->willReturnCallback(
            static function (string $sql, array $params) use (&$executeCalls): int {
                $executeCalls[] = $params;

                return 1;
            },
        );

        $adapter = new SimulatedSupplierAdapter($connection, $this->tmpProjectDir);

        $result = $adapter->fetchDeliveries(
            $this->makeSupplier(),
            $this->makeAccount(),
            new \DateTimeImmutable('-1 day'),
            new \DateTimeImmutable(),
        );

        self::assertCount(1, $result);
        self::assertSame('SIM-DNR-001', $result[0]->deliveryNoteReference);
        self::assertSame('SIM-EXT-42', $result[0]->purchaseOrderExternalReference);
        self::assertCount(1, $executeCalls); // UPDATE for fetched_at
    }

    public function testFetchDeliveriesUsesEmptyExternalReferenceWhenOrderRowMissing(): void
    {
        $rows = [
            [
                'id'                      => 'delivery-row-2',
                'simulated_order_id'      => 'order-row-2',
                'purchase_order_id'       => 'po-bin',
                'delivery_note_reference' => 'SIM-DNR-002',
                'payload_json'            => json_encode([], \JSON_THROW_ON_ERROR),
                'available_at'            => '2026-01-15 10:00:00',
                'fetched_at'              => null,
            ],
        ];

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($rows);
        // Order lookup returns false (not found)
        $connection->method('fetchAssociative')->willReturn(false);
        $connection->method('executeStatement')->willReturn(1);

        $adapter = new SimulatedSupplierAdapter($connection, $this->tmpProjectDir);

        $result = $adapter->fetchDeliveries(
            $this->makeSupplier(),
            $this->makeAccount(),
            new \DateTimeImmutable('-1 day'),
            new \DateTimeImmutable(),
        );

        self::assertCount(1, $result);
        self::assertSame('', $result[0]->purchaseOrderExternalReference);
    }

    public function testFetchCatalogReturnsEmptyWhenYamlMissing(): void
    {
        $adapter = new SimulatedSupplierAdapter(
            $this->createStub(Connection::class),
            $this->tmpProjectDir,
        );

        $result = $adapter->fetchCatalog($this->makeSupplier());

        self::assertSame([], $result);
    }

    public function testFetchCatalogParsesYaml(): void
    {
        $supplier     = $this->makeSupplier();
        $supplierCode = mb_strtolower($supplier->code()->toString());
        $yamlPath     = $this->tmpProjectDir . '/resources/simulated-catalogs/' . $supplierCode . '.yaml';
        file_put_contents($yamlPath, <<<YAML
products:
  - code: P-001
    name: Antibiotic
    gtin: '1234567890123'
    price_minor: 1299
    currency: EUR
    unit: TABLET
    packaging_amount: '100'
  - code: P-002
    name: Bandage
    price_minor: 350
YAML
        );

        $adapter = new SimulatedSupplierAdapter(
            $this->createStub(Connection::class),
            $this->tmpProjectDir,
        );

        $result = $adapter->fetchCatalog($supplier);

        self::assertCount(2, $result);
        self::assertSame('P-001', $result[0]->supplierProductCode);
        self::assertSame('Antibiotic', $result[0]->name);
        self::assertSame('1234567890123', $result[0]->gtin);
        self::assertSame(1299, $result[0]->priceMinor);
        self::assertSame('EUR', $result[0]->currency);
        self::assertSame('TABLET', $result[0]->unit);
        self::assertSame('100', $result[0]->packagingAmount);

        // 2nd product has fewer fields → nullable values default
        self::assertSame('P-002', $result[1]->supplierProductCode);
        self::assertNull($result[1]->gtin);
        self::assertNull($result[1]->unit);
        self::assertNull($result[1]->packagingAmount);
        self::assertSame('EUR', $result[1]->currency);
    }

    private function makeOrderWithLines(): PurchaseOrder
    {
        $po = PurchaseOrder::create(
            id: PurchaseOrderId::fromString(self::PO_UUID),
            clinicId: ClinicId::fromString(self::CLINIC_UUID),
            supplierId: SupplierId::fromString(self::SUPPLIER_UUID),
            supplierAccountId: SupplierAccountId::fromString(self::ACCOUNT_UUID),
            orderNumber: PurchaseOrderNumber::fromString('PO-2026-000020'),
            currency: CurrencyCode::fromString('EUR'),
            deliveryAddress: Address::create(null, null, null, null, null),
        );
        $_ = $po->pullDomainEvents();

        $po->addLine(
            lineId: PurchaseOrderLineId::fromString(self::LINE_UUID),
            articleId: ArticleId::fromString(self::ARTICLE_UUID),
            catalogEntryId: SupplierCatalogEntryId::fromString(self::CATALOG_UUID),
            orderedAmount: '5',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: Money::fromMinorUnits(1000, CurrencyCode::fromString('EUR')),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );
        $_ = $po->pullDomainEvents();

        return $po;
    }

    private function makeSupplier(): Supplier
    {
        return Supplier::register(
            id: SupplierId::fromString(self::SUPPLIER_UUID),
            name: SupplierName::fromString('Test'),
            code: SupplierCode::fromString('TEST'),
            type: SupplierType::CENTRALE,
            countryCode: CountryCode::fromString('FR'),
            defaultCurrency: CurrencyCode::fromString('EUR'),
            integrationMode: SupplierIntegrationMode::SIMULATION,
            adapterIdentifier: 'simulated',
        );
    }

    private function makeAccount(): SupplierAccount
    {
        return SupplierAccount::create(
            id: SupplierAccountId::fromString(self::ACCOUNT_UUID),
            clinicId: ClinicId::fromString(self::CLINIC_UUID),
            supplierId: SupplierId::fromString(self::SUPPLIER_UUID),
            customerCode: CustomerCode::fromString('TEST-CUST'),
        );
    }
}
