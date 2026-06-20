<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Domain\SupplierPricing;

use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierPricing\Event\SupplierPricingNegotiated;
use App\Context\Procurement\Domain\SupplierPricing\Event\SupplierPricingRemoved;
use App\Context\Procurement\Domain\SupplierPricing\SupplierPricing;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\NegotiatedPrice;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\SupplierPricingId;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class SupplierPricingTest extends TestCase
{
    public function testNegotiateRaisesEvent(): void
    {
        $pricing = $this->makePricing();
        $events  = $pricing->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SupplierPricingNegotiated::class, $events[0]);
    }

    public function testRemoveRaisesEvent(): void
    {
        $pricing = $this->makePricing();
        $_       = $pricing->pullDomainEvents();
        $pricing->remove(new \DateTimeImmutable());
        $events = $pricing->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SupplierPricingRemoved::class, $events[0]);
        self::assertTrue($pricing->isRemoved());
    }

    public function testExpiresAtBeforeNegotiatedAtThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SupplierPricing::negotiate(
            id: SupplierPricingId::fromString('01932b00-0000-7000-8000-000000000020'),
            clinicId: ClinicId::fromString('01932b00-0000-7000-8000-000000000003'),
            supplierId: SupplierId::fromString('01932b00-0000-7000-8000-000000000001'),
            catalogEntryId: SupplierCatalogEntryId::fromString('01932b00-0000-7000-8000-000000000010'),
            negotiatedPrice: NegotiatedPrice::create(Money::fromMinorUnits(1000, CurrencyCode::fromString('EUR'))),
            expiresAt: new \DateTimeImmutable('2025-12-31'), // before negotiatedAt
            negotiatedAt: new \DateTimeImmutable('2026-01-01'),
        );
    }

    public function testNegotiatedPriceAmountMustBePositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NegotiatedPrice::create(Money::fromMinorUnits(0, CurrencyCode::fromString('EUR')));
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
