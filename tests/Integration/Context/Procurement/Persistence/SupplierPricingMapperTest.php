<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Procurement\Persistence;

use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierPricing\SupplierPricing;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\NegotiatedPrice;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\SupplierPricingId;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Mapper\SupplierPricingMapper;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * Pure PHP round-trip test for SupplierPricingMapper.
 * Covers NegotiatedPrice with optional discountPercentage and expiresAt.
 */
final class SupplierPricingMapperTest extends TestCase
{
    private SupplierPricingMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new SupplierPricingMapper();
    }

    public function testRoundTripWithDiscountAndNotes(): void
    {
        $now = new \DateTimeImmutable('2026-01-15T10:00:00+00:00');

        $pricing = SupplierPricing::reconstitute(
            id: SupplierPricingId::fromString('01932b00-0000-7000-8000-000000000200'),
            clinicId: ClinicId::fromString('01932b00-0000-7000-8000-000000000003'),
            supplierId: SupplierId::fromString('01932b00-0000-7000-8000-000000000001'),
            catalogEntryId: SupplierCatalogEntryId::fromString('01932b00-0000-7000-8000-000000000100'),
            negotiatedPrice: NegotiatedPrice::create(
                Money::fromMinorUnits(2000, CurrencyCode::fromString('EUR')),
                '10.50',
                'Annual contract discount',
            ),
            expiresAt: new \DateTimeImmutable('2026-12-31'),
            negotiatedAt: $now,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $entity        = $this->mapper->toEntity($pricing);
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame($pricing->id()->toString(), $reconstituted->id()->toString());
        self::assertSame($pricing->clinicId()->toString(), $reconstituted->clinicId()->toString());
        self::assertSame($pricing->supplierId()->toString(), $reconstituted->supplierId()->toString());
        self::assertSame($pricing->catalogEntryId()->toString(), $reconstituted->catalogEntryId()->toString());
        self::assertSame(2000, $reconstituted->negotiatedPrice()->amount->minorUnits());
        self::assertSame('EUR', $reconstituted->negotiatedPrice()->amount->currency()->toString());
        self::assertSame('10.50', $reconstituted->negotiatedPrice()->discountPercentage);
        self::assertSame('Annual contract discount', $reconstituted->negotiatedPrice()->notes);
        $reconstitutedExpiresAt = $reconstituted->expiresAt();
        self::assertNotNull($reconstitutedExpiresAt);
        self::assertSame(
            (new \DateTimeImmutable('2026-12-31'))->getTimestamp(),
            $reconstitutedExpiresAt->getTimestamp()
        );
        self::assertSame($now->getTimestamp(), $reconstituted->negotiatedAt()->getTimestamp());
        self::assertSame(1, $reconstituted->version());
    }

    public function testRoundTripWithoutDiscountAndWithoutExpiry(): void
    {
        $now = new \DateTimeImmutable('2026-01-15T10:00:00+00:00');

        $pricing = SupplierPricing::reconstitute(
            id: SupplierPricingId::fromString('01932b00-0000-7000-8000-000000000201'),
            clinicId: ClinicId::fromString('01932b00-0000-7000-8000-000000000003'),
            supplierId: SupplierId::fromString('01932b00-0000-7000-8000-000000000001'),
            catalogEntryId: SupplierCatalogEntryId::fromString('01932b00-0000-7000-8000-000000000101'),
            negotiatedPrice: NegotiatedPrice::create(
                Money::fromMinorUnits(1500, CurrencyCode::fromString('EUR')),
                null, // no discount
                null, // no notes
            ),
            expiresAt: null,
            negotiatedAt: $now,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $entity        = $this->mapper->toEntity($pricing);
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame(1500, $reconstituted->negotiatedPrice()->amount->minorUnits());
        self::assertNull($reconstituted->negotiatedPrice()->discountPercentage);
        self::assertNull($reconstituted->negotiatedPrice()->notes);
        self::assertNull($reconstituted->expiresAt());
        self::assertSame(1, $reconstituted->version());
    }
}
