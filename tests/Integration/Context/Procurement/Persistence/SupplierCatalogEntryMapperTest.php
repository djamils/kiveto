<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Procurement\Persistence;

use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierCatalog\SupplierCatalogEntry;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\CatalogPrice;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\Gtin;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryStatus;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductCode;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductName;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Mapper\SupplierCatalogEntryMapper;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * Pure PHP round-trip test for SupplierCatalogEntryMapper.
 * Covers CatalogPrice with optional validTo field.
 */
final class SupplierCatalogEntryMapperTest extends TestCase
{
    private SupplierCatalogEntryMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new SupplierCatalogEntryMapper();
    }

    public function testRoundTripWithOpenEndedPrice(): void
    {
        $now   = new \DateTimeImmutable('2026-01-15T10:00:00+00:00');
        $entry = SupplierCatalogEntry::reconstitute(
            id: SupplierCatalogEntryId::fromString('01932b00-0000-7000-8000-000000000100'),
            supplierId: SupplierId::fromString('01932b00-0000-7000-8000-000000000001'),
            productCode: SupplierProductCode::fromString('PROD-001'),
            name: SupplierProductName::fromString('Amoxicilline 500mg'),
            gtin: Gtin::fromString('3401234567890'),
            catalogPrice: CatalogPrice::create(
                Money::fromMinorUnits(2500, CurrencyCode::fromString('EUR')),
                new \DateTimeImmutable('2026-01-01'),
                null, // open-ended price
            ),
            packagingUnit: UnitOfMeasure::fromString('UNIT'),
            packagingAmount: '12',
            status: SupplierCatalogEntryStatus::ACTIVE,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $entity        = $this->mapper->toEntity($entry);
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame($entry->id()->toString(), $reconstituted->id()->toString());
        self::assertSame($entry->supplierId()->toString(), $reconstituted->supplierId()->toString());
        self::assertSame('PROD-001', $reconstituted->productCode()->toString());
        self::assertSame('Amoxicilline 500mg', $reconstituted->name()->toString());
        $gtin = $reconstituted->gtin();
        self::assertNotNull($gtin);
        self::assertSame('3401234567890', $gtin->toString());
        self::assertSame(2500, $reconstituted->catalogPrice()->amount->minorUnits());
        self::assertSame('EUR', $reconstituted->catalogPrice()->amount->currency()->toString());
        self::assertNull($reconstituted->catalogPrice()->validTo);
        self::assertSame('UNIT', $reconstituted->packagingUnit()?->toString());
        self::assertSame('12', $reconstituted->packagingAmount());
        self::assertSame(SupplierCatalogEntryStatus::ACTIVE, $reconstituted->status());
        self::assertSame(1, $reconstituted->version());
    }

    public function testRoundTripWithValidTo(): void
    {
        $now     = new \DateTimeImmutable('2026-01-15T10:00:00+00:00');
        $validTo = new \DateTimeImmutable('2026-12-31T23:59:59+00:00');

        $entry = SupplierCatalogEntry::reconstitute(
            id: SupplierCatalogEntryId::fromString('01932b00-0000-7000-8000-000000000101'),
            supplierId: SupplierId::fromString('01932b00-0000-7000-8000-000000000001'),
            productCode: SupplierProductCode::fromString('PROD-002'),
            name: SupplierProductName::fromString('Vaccin Rage'),
            gtin: null,
            catalogPrice: CatalogPrice::create(
                Money::fromMinorUnits(3200, CurrencyCode::fromString('EUR')),
                new \DateTimeImmutable('2026-01-01'),
                $validTo,
            ),
            packagingUnit: null,
            packagingAmount: null,
            status: SupplierCatalogEntryStatus::DISCONTINUED,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $entity        = $this->mapper->toEntity($entry);
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertNull($reconstituted->gtin());
        $reconstitutedValidTo = $reconstituted->catalogPrice()->validTo;
        self::assertNotNull($reconstitutedValidTo);
        self::assertSame($validTo->getTimestamp(), $reconstitutedValidTo->getTimestamp());
        self::assertNull($reconstituted->packagingUnit());
        self::assertNull($reconstituted->packagingAmount());
        self::assertSame(SupplierCatalogEntryStatus::DISCONTINUED, $reconstituted->status());
    }
}
