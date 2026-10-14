<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Domain\Package;

use App\Context\Catalog\Domain\Package\Event\PackageArchived;
use App\Context\Catalog\Domain\Package\Event\PackageComponentAdded;
use App\Context\Catalog\Domain\Package\Event\PackageComponentRemoved;
use App\Context\Catalog\Domain\Package\Event\PackageFixedPriceChanged;
use App\Context\Catalog\Domain\Package\Event\PackageRestored;
use App\Context\Catalog\Domain\Package\Exception\InvalidPackagePricingModeException;
use App\Context\Catalog\Domain\Package\Package;
use App\Context\Catalog\Domain\Package\PackageComponent;
use App\Context\Catalog\Domain\Package\ValueObject\PackageComponentId;
use App\Context\Catalog\Domain\Package\ValueObject\PackagePricingMode;
use App\Context\Catalog\Domain\Package\ValueObject\PackageStatus;
use App\Context\Catalog\Domain\Package\ValueObject\Quantity;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemRef;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Package::class)]
#[CoversClass(PackageComponent::class)]
final class PackageCompositionTest extends TestCase
{
    public function testAReconstitutedPackageExposesItsState(): void
    {
        $package = F::package();

        self::assertSame(F::PACKAGE_ID, $package->id()->toString());
        self::assertSame(F::CLINIC_ID, $package->clinicId()->toString());
        self::assertSame('Forfait stérilisation', $package->name()->toString());
        self::assertSame('PKG-STER', $package->code()->toString());
        self::assertSame('veterinary.act.surgery', $package->taxCategory()->toString());
        self::assertSame(PackagePricingMode::FIXED_PRICE, $package->pricingMode());
        self::assertSame(25000, $package->fixedPrice()?->minorUnits());
        self::assertSame([], $package->components());
        self::assertSame(PackageStatus::ACTIVE, $package->status());
        self::assertSame('2026-01-01 10:00:00', $package->createdAt()->format('Y-m-d H:i:s'));
        self::assertSame('2026-01-01 10:00:00', $package->updatedAt()->format('Y-m-d H:i:s'));
    }

    public function testComponentsCanBeAddedThenRemoved(): void
    {
        $package = F::package();

        $actComponentId = $package->addComponent(
            CatalogItemRef::fromPrimitives('ACT', F::ACT_ID),
            Quantity::of(1),
            10,
            F::now(),
        );
        $package->addComponent(CatalogItemRef::fromPrimitives('ARTICLE', F::ARTICLE_ID), Quantity::of(2), 0, F::now());

        self::assertCount(2, $package->components());
        self::assertTrue($package->components()[0]->id()->equals($actComponentId));
        self::assertSame(1, $package->components()[0]->quantity()->value());
        self::assertSame(10, $package->components()[0]->weight());

        $package->removeComponent($actComponentId, F::now());

        self::assertCount(1, $package->components());
        self::assertSame(F::ARTICLE_ID, $package->components()[0]->itemRef()->id());
        self::assertSame(F::now(), $package->updatedAt());

        $events = $package->pullDomainEvents();
        self::assertCount(3, $events);
        self::assertInstanceOf(PackageComponentAdded::class, $events[0]);
        self::assertInstanceOf(PackageComponentAdded::class, $events[1]);
        self::assertInstanceOf(PackageComponentRemoved::class, $events[2]);
    }

    public function testRemovingAnUnknownComponentChangesNothing(): void
    {
        $package = F::package(components: [
            PackageComponent::create(
                PackageComponentId::fromString('01960000-0000-7000-8000-00000000d001'),
                CatalogItemRef::fromPrimitives('ACT', F::ACT_ID),
                Quantity::of(1),
                0,
            ),
        ]);

        $package->removeComponent(PackageComponentId::fromString('01960000-0000-7000-8000-00000000d099'), F::now());

        self::assertCount(1, $package->components());
        self::assertSame([], $package->pullDomainEvents());
    }

    public function testAFixedPricePackageCanBeRepriced(): void
    {
        $package = F::package();

        $package->changeFixedPrice(F::eur(27500), F::now());

        self::assertSame(27500, $package->fixedPrice()?->minorUnits());

        $events = $package->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PackageFixedPriceChanged::class, $events[0]);
    }

    public function testASumOfComponentsPackageHasNoFixedPriceToChange(): void
    {
        $package = F::package(PackagePricingMode::SUM_OF_COMPONENTS);

        $this->expectException(InvalidPackagePricingModeException::class);

        $package->changeFixedPrice(F::eur(27500), F::now());
    }

    public function testArchivingAndRestoringAreIdempotent(): void
    {
        $package = F::package();

        $package->restore(F::now());
        $package->archive(F::now());
        $package->archive(F::now());
        $package->restore(F::now());

        $events = $package->pullDomainEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(PackageArchived::class, $events[0]);
        self::assertInstanceOf(PackageRestored::class, $events[1]);
    }
}
