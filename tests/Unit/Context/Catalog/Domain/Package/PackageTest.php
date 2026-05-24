<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Domain\Package;

use App\Context\Catalog\Domain\Package\Exception\InvalidPackagePricingModeException;
use App\Context\Catalog\Domain\Package\Package;
use App\Context\Catalog\Domain\Package\ValueObject\PackageCode;
use App\Context\Catalog\Domain\Package\ValueObject\PackageId;
use App\Context\Catalog\Domain\Package\ValueObject\PackageName;
use App\Context\Catalog\Domain\Package\ValueObject\PackagePricingMode;
use App\Context\Catalog\Domain\Package\ValueObject\PackageStatus;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use PHPUnit\Framework\TestCase;

final class PackageTest extends TestCase
{
    public function testCreateFixedPriceRequiresFixedPrice(): void
    {
        $this->expectException(InvalidPackagePricingModeException::class);

        $now = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        Package::create(
            id: PackageId::fromString('01950000-0000-7000-0000-000000000001'),
            clinicId: ClinicId::fromString('01950000-0000-7000-0000-000000000002'),
            name: PackageName::fromString('Test'),
            code: PackageCode::fromString('PKG-TEST'),
            taxCategory: TaxCategoryCode::fromString('veterinary.act.surgery'),
            pricingMode: PackagePricingMode::FIXED_PRICE,
            fixedPrice: null,
            createdAt: $now,
        );
    }

    public function testCreateSumOfComponentsNoFixedPrice(): void
    {
        $this->expectException(InvalidPackagePricingModeException::class);

        $now = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        Package::create(
            id: PackageId::fromString('01950000-0000-7000-0000-000000000001'),
            clinicId: ClinicId::fromString('01950000-0000-7000-0000-000000000002'),
            name: PackageName::fromString('Test'),
            code: PackageCode::fromString('PKG-TEST'),
            taxCategory: TaxCategoryCode::fromString('veterinary.act.surgery'),
            pricingMode: PackagePricingMode::SUM_OF_COMPONENTS,
            fixedPrice: Money::fromMinorUnits(1000, CurrencyCode::fromString('EUR')),
            createdAt: $now,
        );
    }

    public function testArchiveIdempotent(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $pkg = $this->makePackage();

        $pkg->archive($now);
        $pkg->archive($now);

        $events = $pkg->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertSame(PackageStatus::ARCHIVED, $pkg->status());
    }

    public function testRestoreIdempotent(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $pkg = $this->makePackage();

        $pkg->restore($now);
        $pkg->restore($now);

        self::assertSame([], $pkg->pullDomainEvents());
        self::assertSame(PackageStatus::ACTIVE, $pkg->status());
    }

    public function testFixedPricePackageCreation(): void
    {
        $fixedPrice = Money::fromMinorUnits(38000, CurrencyCode::fromString('EUR'));
        $pkg        = $this->makePackage(PackagePricingMode::FIXED_PRICE, $fixedPrice);

        self::assertSame(PackagePricingMode::FIXED_PRICE, $pkg->pricingMode());
        self::assertNotNull($pkg->fixedPrice());
        self::assertSame(38000, $pkg->fixedPrice()->minorUnits());
    }

    private function makePackage(?PackagePricingMode $mode = null, ?Money $fixedPrice = null): Package
    {
        $now  = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $mode = $mode ?? PackagePricingMode::SUM_OF_COMPONENTS;
        $pkg  = Package::create(
            id: PackageId::fromString('01950000-0000-7000-0000-000000000001'),
            clinicId: ClinicId::fromString('01950000-0000-7000-0000-000000000002'),
            name: PackageName::fromString('Pack Stérilisation'),
            code: PackageCode::fromString('PKG-STERIL'),
            taxCategory: TaxCategoryCode::fromString('veterinary.act.surgery'),
            pricingMode: $mode,
            fixedPrice: $fixedPrice,
            createdAt: $now,
        );
        $_ = $pkg->pullDomainEvents();

        return $pkg;
    }
}
