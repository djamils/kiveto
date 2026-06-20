<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Domain\SupplierPricing;

use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierCatalog\SupplierCatalogEntry;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\CatalogPrice;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductCode;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductName;
use App\Context\Procurement\Domain\SupplierPricing\Exception\NoEffectivePriceException;
use App\Context\Procurement\Domain\SupplierPricing\Service\PriceResolver;
use App\Context\Procurement\Domain\SupplierPricing\SupplierPricing;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\NegotiatedPrice;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\SupplierPricingId;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\SupplierPricingSource;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class PriceResolverTest extends TestCase
{
    private PriceResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new PriceResolver();
    }

    public function testNonExpiredNegotiatedPricingReturnsNegotiated(): void
    {
        $entry   = $this->makeEntry(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'));
        $pricing = $this->makePricing(new \DateTimeImmutable('2026-12-31'));
        $result  = $this->resolver->resolve($pricing, $entry, new \DateTimeImmutable('2026-06-01'));
        self::assertSame(SupplierPricingSource::NEGOTIATED, $result->source);
        self::assertSame(1000, $result->amount->minorUnits());
    }

    public function testExpiredNegotiatedPricingFallsBackToCatalog(): void
    {
        $entry   = $this->makeEntry(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'));
        $pricing = $this->makePricing(new \DateTimeImmutable('2026-03-01'));
        $result  = $this->resolver->resolve($pricing, $entry, new \DateTimeImmutable('2026-06-01'));
        self::assertSame(SupplierPricingSource::CATALOG_DEFAULT, $result->source);
        self::assertSame(1500, $result->amount->minorUnits());
    }

    public function testNullPricingWithValidCatalogReturnsCatalogDefault(): void
    {
        $entry  = $this->makeEntry(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'));
        $result = $this->resolver->resolve(null, $entry, new \DateTimeImmutable('2026-06-01'));
        self::assertSame(SupplierPricingSource::CATALOG_DEFAULT, $result->source);
    }

    public function testNullPricingWithExpiredCatalogThrows(): void
    {
        $entry = $this->makeEntry(new \DateTimeImmutable('2025-01-01'), new \DateTimeImmutable('2025-12-31'));
        $this->expectException(NoEffectivePriceException::class);
        $this->resolver->resolve(null, $entry, new \DateTimeImmutable('2026-06-01'));
    }

    public function testNegotiatedPricingWithNullExpiresAtNeverExpires(): void
    {
        $entry   = $this->makeEntry(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'));
        $pricing = $this->makePricing(null); // never expires
        $result  = $this->resolver->resolve($pricing, $entry, new \DateTimeImmutable('2030-06-01'));
        self::assertSame(SupplierPricingSource::NEGOTIATED, $result->source);
    }

    public function testNullPricingWithForeverValidCatalogReturnsDefault(): void
    {
        $entry  = $this->makeEntry(new \DateTimeImmutable('2026-01-01'), null); // validTo = null
        $result = $this->resolver->resolve(null, $entry, new \DateTimeImmutable('2035-01-01'));
        self::assertSame(SupplierPricingSource::CATALOG_DEFAULT, $result->source);
    }

    private function makeEntry(\DateTimeImmutable $validFrom, ?\DateTimeImmutable $validTo = null): SupplierCatalogEntry
    {
        return SupplierCatalogEntry::add(
            id: SupplierCatalogEntryId::fromString('01932b00-0000-7000-8000-000000000010'),
            supplierId: SupplierId::fromString('01932b00-0000-7000-8000-000000000001'),
            productCode: SupplierProductCode::fromString('CVT-001'),
            name: SupplierProductName::fromString('Test Product'),
            gtin: null,
            catalogPrice: CatalogPrice::create(
                Money::fromMinorUnits(1500, CurrencyCode::fromString('EUR')),
                $validFrom,
                $validTo,
            ),
        );
    }

    private function makePricing(?\DateTimeImmutable $expiresAt = null): SupplierPricing
    {
        return SupplierPricing::negotiate(
            id: SupplierPricingId::fromString('01932b00-0000-7000-8000-000000000020'),
            clinicId: ClinicId::fromString('01932b00-0000-7000-8000-000000000003'),
            supplierId: SupplierId::fromString('01932b00-0000-7000-8000-000000000001'),
            catalogEntryId: SupplierCatalogEntryId::fromString('01932b00-0000-7000-8000-000000000010'),
            negotiatedPrice: NegotiatedPrice::create(Money::fromMinorUnits(1000, CurrencyCode::fromString('EUR'))),
            expiresAt: $expiresAt,
            negotiatedAt: new \DateTimeImmutable('2026-01-01'),
        );
    }
}
