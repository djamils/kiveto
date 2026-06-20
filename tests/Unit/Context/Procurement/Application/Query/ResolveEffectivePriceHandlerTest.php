<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Query;

use App\Context\Procurement\Application\Query\ResolveEffectivePrice\ResolveEffectivePrice;
use App\Context\Procurement\Application\Query\ResolveEffectivePrice\ResolveEffectivePriceHandler;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierCatalog\Exception\SupplierCatalogEntryNotFoundException;
use App\Context\Procurement\Domain\SupplierCatalog\Repository\SupplierCatalogEntryRepositoryInterface;
use App\Context\Procurement\Domain\SupplierCatalog\SupplierCatalogEntry;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\CatalogPrice;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductCode;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductName;
use App\Context\Procurement\Domain\SupplierPricing\Repository\SupplierPricingRepositoryInterface;
use App\Context\Procurement\Domain\SupplierPricing\Service\PriceResolver;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\SupplierPricingSource;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class ResolveEffectivePriceHandlerTest extends TestCase
{
    private const string CLINIC_UUID = '01932b00-0000-7000-8000-000000000010';
    private const string ENTRY_UUID  = '01932b00-0000-7000-8000-000000000003';

    public function testItResolvesToCatalogPriceWhenNoPricingExists(): void
    {
        $entry = SupplierCatalogEntry::add(
            id: SupplierCatalogEntryId::fromString(self::ENTRY_UUID),
            supplierId: SupplierId::fromString('01932b00-0000-7000-8000-000000000001'),
            productCode: SupplierProductCode::fromString('PROD-001'),
            name: SupplierProductName::fromString('Product'),
            gtin: null,
            catalogPrice: CatalogPrice::create(
                Money::fromMinorUnits(1500, CurrencyCode::fromString('EUR')),
                new \DateTimeImmutable('2023-01-01'),
                null,
            ),
        );

        $catalogRepository = $this->createStub(SupplierCatalogEntryRepositoryInterface::class);
        $catalogRepository->method('findById')->willReturn($entry);

        $pricingRepository = $this->createStub(SupplierPricingRepositoryInterface::class);
        $pricingRepository->method('findByClinicAndEntry')->willReturn(null);

        $handler = new ResolveEffectivePriceHandler($pricingRepository, $catalogRepository, new PriceResolver());
        $result  = $handler(new ResolveEffectivePrice(
            clinicId: self::CLINIC_UUID,
            entryId: self::ENTRY_UUID,
            referenceDate: '2024-01-01',
        ));

        self::assertSame(1500, $result['amountMinor']);
        self::assertSame('EUR', $result['currency']);
        self::assertSame(SupplierPricingSource::CATALOG_DEFAULT->value, $result['source']);
    }

    public function testItThrowsWhenEntryNotFound(): void
    {
        $catalogRepository = $this->createStub(SupplierCatalogEntryRepositoryInterface::class);
        $catalogRepository->method('findById')->willReturn(null);

        $pricingRepository = $this->createStub(SupplierPricingRepositoryInterface::class);

        $handler = new ResolveEffectivePriceHandler($pricingRepository, $catalogRepository, new PriceResolver());

        $this->expectException(SupplierCatalogEntryNotFoundException::class);

        $handler(new ResolveEffectivePrice(
            clinicId: self::CLINIC_UUID,
            entryId: self::ENTRY_UUID,
            referenceDate: '2024-01-01',
        ));
    }
}
