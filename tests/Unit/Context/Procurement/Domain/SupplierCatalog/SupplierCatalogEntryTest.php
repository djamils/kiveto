<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Domain\SupplierCatalog;

use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierCatalog\Event\SupplierCatalogEntryAdded;
use App\Context\Procurement\Domain\SupplierCatalog\Event\SupplierCatalogEntryDiscontinued;
use App\Context\Procurement\Domain\SupplierCatalog\Event\SupplierCatalogPriceChanged;
use App\Context\Procurement\Domain\SupplierCatalog\SupplierCatalogEntry;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\CatalogPrice;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\Gtin;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryStatus;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductCode;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductName;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class SupplierCatalogEntryTest extends TestCase
{
    public function testAddRaisesSupplierCatalogEntryAdded(): void
    {
        $entry  = $this->makeEntry();
        $events = $entry->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SupplierCatalogEntryAdded::class, $events[0]);
    }

    public function testDiscontinueTransitionsStatus(): void
    {
        $entry = $this->makeEntry();
        $_     = $entry->pullDomainEvents();
        $entry->discontinue(new \DateTimeImmutable());
        $events = $entry->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SupplierCatalogEntryDiscontinued::class, $events[0]);
        self::assertSame(SupplierCatalogEntryStatus::DISCONTINUED, $entry->status());
    }

    public function testUpdatePriceRaisesSupplierCatalogPriceChanged(): void
    {
        $entry    = $this->makeEntry();
        $_        = $entry->pullDomainEvents();
        $newPrice = CatalogPrice::create(
            Money::fromMinorUnits(2000, CurrencyCode::fromString('EUR')),
            new \DateTimeImmutable('2027-01-01'),
        );
        $entry->updatePrice($newPrice, new \DateTimeImmutable());
        $events = $entry->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(SupplierCatalogPriceChanged::class, $events[0]);
    }

    public function testGtinValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Gtin::fromString('12345'); // 5 digits — invalid
    }

    public function testGtinAccepts13Digits(): void
    {
        $gtin = Gtin::fromString('1234567890123');
        self::assertSame('1234567890123', $gtin->toString());
    }

    public function testCatalogPriceValidToMustBeAfterValidFrom(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CatalogPrice::create(
            Money::fromMinorUnits(1000, CurrencyCode::fromString('EUR')),
            new \DateTimeImmutable('2026-12-31'),
            new \DateTimeImmutable('2026-01-01'), // before validFrom
        );
    }

    public function testCatalogPriceIsValidAt(): void
    {
        $price = CatalogPrice::create(
            Money::fromMinorUnits(1000, CurrencyCode::fromString('EUR')),
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-12-31'),
        );
        self::assertTrue($price->isValidAt(new \DateTimeImmutable('2026-06-01')));
        self::assertFalse($price->isValidAt(new \DateTimeImmutable('2027-01-01')));
        self::assertFalse($price->isValidAt(new \DateTimeImmutable('2025-12-31')));
    }

    public function testCatalogPriceWithNullValidToIsForeverValid(): void
    {
        $price = CatalogPrice::create(
            Money::fromMinorUnits(1000, CurrencyCode::fromString('EUR')),
            new \DateTimeImmutable('2026-01-01'),
            null, // no expiry
        );
        self::assertTrue($price->isValidAt(new \DateTimeImmutable('2030-01-01')));
        self::assertFalse($price->isValidAt(new \DateTimeImmutable('2025-12-31')));
    }

    private function makeEntry(): SupplierCatalogEntry
    {
        return SupplierCatalogEntry::add(
            id: SupplierCatalogEntryId::fromString('01932b00-0000-7000-8000-000000000010'),
            supplierId: SupplierId::fromString('01932b00-0000-7000-8000-000000000001'),
            productCode: SupplierProductCode::fromString('CVT-001'),
            name: SupplierProductName::fromString('Amoxicilline 500mg'),
            gtin: null,
            catalogPrice: CatalogPrice::create(
                Money::fromMinorUnits(1250, CurrencyCode::fromString('EUR')),
                new \DateTimeImmutable('2026-01-01'),
                new \DateTimeImmutable('2026-12-31'),
            ),
        );
    }
}
