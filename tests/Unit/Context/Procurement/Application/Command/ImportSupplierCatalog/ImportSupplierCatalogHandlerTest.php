<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Command\ImportSupplierCatalog;

use App\Context\Procurement\Application\Command\ImportSupplierCatalog\ImportSupplierCatalog;
use App\Context\Procurement\Application\Command\ImportSupplierCatalog\ImportSupplierCatalogHandler;
use App\Context\Procurement\Application\Dto\CatalogEntryData;
use App\Context\Procurement\Application\Exception\CatalogImportNotSupportedException;
use App\Context\Procurement\Application\Port\SupplierIntegrationAdapterInterface;
use App\Context\Procurement\Application\Service\SupplierIntegrationDispatcher;
use App\Context\Procurement\Domain\Supplier\Exception\SupplierNotFoundException;
use App\Context\Procurement\Domain\Supplier\Repository\SupplierRepositoryInterface;
use App\Context\Procurement\Domain\Supplier\Supplier;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierCode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierIntegrationMode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierName;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierType;
use App\Context\Procurement\Domain\SupplierCatalog\Repository\SupplierCatalogEntryRepositoryInterface;
use App\Context\Procurement\Domain\SupplierCatalog\SupplierCatalogEntry;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\CatalogPrice;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductCode;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductName;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ImportSupplierCatalogHandlerTest extends TestCase
{
    private const string SUPPLIER_UUID = '01932b00-0000-7000-8000-000000000001';
    private const string ENTRY_UUID    = '01932b00-0000-7000-8000-000000000100';

    public function testCommandExposesSupplierId(): void
    {
        $command = new ImportSupplierCatalog(supplierId: self::SUPPLIER_UUID);

        self::assertSame(self::SUPPLIER_UUID, $command->supplierId);
    }

    public function testItThrowsWhenSupplierNotFound(): void
    {
        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findById')->willReturn(null);

        $catalogRepo = $this->createStub(SupplierCatalogEntryRepositoryInterface::class);
        $adapter     = $this->createStub(SupplierIntegrationAdapterInterface::class);
        $adapter->method('identifier')->willReturn('test');
        $adapter->method('mode')->willReturn(SupplierIntegrationMode::SIMULATION);

        $this->expectException(SupplierNotFoundException::class);

        ($this->makeHandler($supplierRepo, $catalogRepo, $adapter))(
            new ImportSupplierCatalog(self::SUPPLIER_UUID)
        );
    }

    public function testItThrowsWhenAdapterDoesNotSupportCatalogImport(): void
    {
        $supplier = $this->makeSupplier();

        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findById')->willReturn($supplier);

        $catalogRepo = $this->createStub(SupplierCatalogEntryRepositoryInterface::class);

        $adapter = $this->createStub(SupplierIntegrationAdapterInterface::class);
        $adapter->method('identifier')->willReturn('test');
        $adapter->method('mode')->willReturn(SupplierIntegrationMode::SIMULATION);
        $adapter->method('supportsCatalogImport')->willReturn(false);

        $this->expectException(CatalogImportNotSupportedException::class);

        ($this->makeHandler($supplierRepo, $catalogRepo, $adapter))(
            new ImportSupplierCatalog(self::SUPPLIER_UUID)
        );
    }

    public function testItReturnsWhenAdapterReturnsEmptyCatalog(): void
    {
        $supplier = $this->makeSupplier();

        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findById')->willReturn($supplier);

        $catalogRepo = $this->createMock(SupplierCatalogEntryRepositoryInterface::class);
        $catalogRepo->expects(self::never())->method('save');

        $adapter = $this->createStub(SupplierIntegrationAdapterInterface::class);
        $adapter->method('identifier')->willReturn('test');
        $adapter->method('mode')->willReturn(SupplierIntegrationMode::SIMULATION);
        $adapter->method('supportsCatalogImport')->willReturn(true);
        $adapter->method('fetchCatalog')->willReturn([]);

        ($this->makeHandler($supplierRepo, $catalogRepo, $adapter))(
            new ImportSupplierCatalog(self::SUPPLIER_UUID)
        );
    }

    public function testItCreatesNewEntryWhenNotExisting(): void
    {
        $supplier = $this->makeSupplier();

        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findById')->willReturn($supplier);

        $catalogRepo = $this->createMock(SupplierCatalogEntryRepositoryInterface::class);
        $catalogRepo->method('findBySupplierAndCode')->willReturn(null);
        $catalogRepo->expects(self::once())->method('save');

        $adapter = $this->createStub(SupplierIntegrationAdapterInterface::class);
        $adapter->method('identifier')->willReturn('test');
        $adapter->method('mode')->willReturn(SupplierIntegrationMode::SIMULATION);
        $adapter->method('supportsCatalogImport')->willReturn(true);
        $adapter->method('fetchCatalog')->willReturn([
            new CatalogEntryData(
                supplierProductCode: 'PROD-001',
                name: 'Test Product',
                gtin: '1234567890123',
                priceMinor: 1299,
                currency: 'EUR',
                unit: 'UNIT',
                packagingAmount: '10',
            ),
        ]);

        ($this->makeHandler($supplierRepo, $catalogRepo, $adapter))(
            new ImportSupplierCatalog(self::SUPPLIER_UUID)
        );
    }

    public function testItUpdatesExistingEntry(): void
    {
        $supplier = $this->makeSupplier();
        $existing = $this->makeCatalogEntry($supplier->id());

        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findById')->willReturn($supplier);

        $catalogRepo = $this->createMock(SupplierCatalogEntryRepositoryInterface::class);
        $catalogRepo->method('findBySupplierAndCode')->willReturn($existing);
        $catalogRepo->expects(self::once())->method('save');

        $adapter = $this->createStub(SupplierIntegrationAdapterInterface::class);
        $adapter->method('identifier')->willReturn('test');
        $adapter->method('mode')->willReturn(SupplierIntegrationMode::SIMULATION);
        $adapter->method('supportsCatalogImport')->willReturn(true);
        $adapter->method('fetchCatalog')->willReturn([
            new CatalogEntryData(
                supplierProductCode: 'PROD-EXISTING',
                name: 'Updated Product Name',
                gtin: null,
                priceMinor: 2500,
                currency: 'EUR',
                unit: null,
                packagingAmount: null,
            ),
        ]);

        ($this->makeHandler($supplierRepo, $catalogRepo, $adapter))(
            new ImportSupplierCatalog(self::SUPPLIER_UUID)
        );
    }

    private function makeHandler(
        SupplierRepositoryInterface $supplierRepo,
        SupplierCatalogEntryRepositoryInterface $catalogRepo,
        SupplierIntegrationAdapterInterface $adapter,
    ): ImportSupplierCatalogHandler {
        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturn(self::ENTRY_UUID);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-15'));

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(static function (callable $fn): void {
            $fn();
        });

        $eventBus = $this->createStub(EventBusInterface::class);

        return new ImportSupplierCatalogHandler(
            $supplierRepo,
            $catalogRepo,
            new SupplierIntegrationDispatcher([$adapter]),
            new DomainEventPublisher($eventBus),
            $uuidGenerator,
            $clock,
            $em,
            new NullLogger(),
        );
    }

    private function makeSupplier(): Supplier
    {
        return Supplier::register(
            id: SupplierId::fromString(self::SUPPLIER_UUID),
            name: SupplierName::fromString('Test Supplier'),
            code: SupplierCode::fromString('TEST'),
            type: SupplierType::CENTRALE,
            countryCode: CountryCode::fromString('FR'),
            defaultCurrency: CurrencyCode::fromString('EUR'),
            integrationMode: SupplierIntegrationMode::SIMULATION,
            adapterIdentifier: 'test',
        );
    }

    private function makeCatalogEntry(SupplierId $supplierId): SupplierCatalogEntry
    {
        return SupplierCatalogEntry::add(
            id: SupplierCatalogEntryId::fromString(self::ENTRY_UUID),
            supplierId: $supplierId,
            productCode: SupplierProductCode::fromString('PROD-EXISTING'),
            name: SupplierProductName::fromString('Old name'),
            gtin: null,
            catalogPrice: CatalogPrice::create(
                Money::fromMinorUnits(1000, CurrencyCode::fromString('EUR')),
                new \DateTimeImmutable('2025-01-01'),
                null,
            ),
        );
    }
}
