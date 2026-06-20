<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Command;

use App\Context\Procurement\Application\Command\AddSupplierCatalogEntry\AddSupplierCatalogEntry;
use App\Context\Procurement\Application\Command\AddSupplierCatalogEntry\AddSupplierCatalogEntryHandler;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierCatalog\Exception\DuplicateSupplierProductCodeException;
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
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class AddSupplierCatalogEntryHandlerTest extends TestCase
{
    private const string ENTRY_UUID    = '01932b00-0000-7000-8000-000000000003';
    private const string SUPPLIER_UUID = '01932b00-0000-7000-8000-000000000001';

    public function testItAddsEntry(): void
    {
        $repository = $this->createMock(SupplierCatalogEntryRepositoryInterface::class);
        $repository->method('findBySupplierAndCode')->willReturn(null);
        $repository->expects(self::once())->method('save');

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $this->makeHandler($repository, $eventBus)(new AddSupplierCatalogEntry(
            supplierId: self::SUPPLIER_UUID,
            productCode: 'PROD-001',
            name: 'Produit test',
            gtin: null,
            catalogPriceMinor: 1500,
            catalogPriceCurrency: 'EUR',
            catalogPriceValidFrom: '2024-01-01',
            catalogPriceValidTo: null,
            packagingUnit: null,
            packagingAmount: null,
        ));
    }

    public function testItThrowsOnDuplicateCode(): void
    {
        $existing = SupplierCatalogEntry::add(
            id: SupplierCatalogEntryId::fromString(self::ENTRY_UUID),
            supplierId: SupplierId::fromString(self::SUPPLIER_UUID),
            productCode: SupplierProductCode::fromString('PROD-001'),
            name: SupplierProductName::fromString('Existing product'),
            gtin: null,
            catalogPrice: CatalogPrice::create(
                Money::fromMinorUnits(1500, CurrencyCode::fromString('EUR')),
                new \DateTimeImmutable('2024-01-01'),
            ),
        );

        $repository = $this->createStub(SupplierCatalogEntryRepositoryInterface::class);
        $repository->method('findBySupplierAndCode')->willReturn($existing);

        $this->expectException(DuplicateSupplierProductCodeException::class);

        $this->makeHandler($repository, $this->createStub(EventBusInterface::class))(new AddSupplierCatalogEntry(
            supplierId: self::SUPPLIER_UUID,
            productCode: 'PROD-001',
            name: 'Duplicate',
            gtin: null,
            catalogPriceMinor: 1500,
            catalogPriceCurrency: 'EUR',
            catalogPriceValidFrom: '2024-01-01',
            catalogPriceValidTo: null,
            packagingUnit: null,
            packagingAmount: null,
        ));
    }

    private function makeHandler(
        SupplierCatalogEntryRepositoryInterface $repository,
        EventBusInterface $eventBus,
    ): AddSupplierCatalogEntryHandler {
        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturn(self::ENTRY_UUID);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2024-01-01'));

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(
            static function (callable $fn): void {
                $fn();
            },
        );

        return new AddSupplierCatalogEntryHandler(
            $repository,
            new DomainEventPublisher($eventBus),
            $uuidGenerator,
            $clock,
            $em,
        );
    }
}
