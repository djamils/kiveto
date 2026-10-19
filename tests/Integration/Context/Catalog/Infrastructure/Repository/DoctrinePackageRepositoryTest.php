<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Catalog\Infrastructure\Repository;

use App\Context\Catalog\Domain\Package\Package;
use App\Context\Catalog\Domain\Package\PackageComponent;
use App\Context\Catalog\Domain\Package\Repository\PackageRepositoryInterface;
use App\Context\Catalog\Domain\Package\ValueObject\PackageCode;
use App\Context\Catalog\Domain\Package\ValueObject\PackageId;
use App\Context\Catalog\Domain\Package\ValueObject\PackageName;
use App\Context\Catalog\Domain\Package\ValueObject\PackagePricingMode;
use App\Context\Catalog\Domain\Package\ValueObject\Quantity;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemRef;
use App\Context\Catalog\Domain\ValueObject\CatalogPrice;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Context\Catalog\Domain\ValueObject\PriceEntryMode;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Repository\DoctrinePackageRepository;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

#[CoversClass(DoctrinePackageRepository::class)]
final class DoctrinePackageRepositoryTest extends KernelTestCase
{
    use Factories;

    public function testACompositionIsSavedAgainAndAgainWithoutCollidingWithItself(): void
    {
        $clinicId  = ClinicId::fromString(Uuid::v7()->toRfc4122());
        $packageId = PackageId::fromString(Uuid::v7()->toRfc4122());
        $now       = new \DateTimeImmutable('2026-08-22 10:00:00');
        $repo      = $this->repository();

        $package = Package::create(
            id: $packageId,
            clinicId: $clinicId,
            name: PackageName::fromString('Pack chiot'),
            code: PackageCode::fromString('PKG-ZZCHIOT'),
            taxCategory: TaxCategoryCode::fromString('veterinary.act.preventive'),
            pricingMode: PackagePricingMode::FIXED_PRICE,
            fixedPrice: CatalogPrice::includingTax(Money::fromMinorUnits(16500, CurrencyCode::fromString('EUR'))),
            createdAt: $now,
        );
        $first = $package->addComponent(CatalogItemRef::fromPrimitives('ACT', Uuid::v7()->toRfc4122()), Quantity::of(2), 0, $now);
        $package->addComponent(CatalogItemRef::fromPrimitives('ARTICLE', Uuid::v7()->toRfc4122()), Quantity::of(1), 1, $now);
        $repo->save($package);

        // Reloaded, then changed: one line goes, one comes, the kept one stays.
        $loaded = $repo->findById($packageId, $clinicId);
        self::assertNotNull($loaded);
        self::assertCount(2, $loaded->components());
        self::assertSame(16500, $loaded->fixedPrice()?->minorUnits());
        self::assertSame(PriceEntryMode::INCLUDING_TAX, $loaded->fixedPrice()->mode());

        $second = $loaded->components()[1]->id();
        $loaded->removeComponent($first, $now);
        $loaded->addComponent(CatalogItemRef::fromPrimitives('ACT', Uuid::v7()->toRfc4122()), Quantity::of(3), 2, $now);
        $loaded->revise(
            PackageName::fromString('Pack chiot complet'),
            TaxCategoryCode::fromString('veterinary.act.vaccination'),
            PackagePricingMode::SUM_OF_COMPONENTS,
            null,
            $now,
        );
        $repo->save($loaded);

        $this->entityManager()->clear();

        $found = $repo->findById($packageId, $clinicId);
        self::assertNotNull($found);
        self::assertSame('Pack chiot complet', $found->name()->toString());
        self::assertSame(PackagePricingMode::SUM_OF_COMPONENTS, $found->pricingMode());
        self::assertNull($found->fixedPrice());
        self::assertSame(
            [[$second->toString(), 1], ['new', 3]],
            array_map(
                static fn (PackageComponent $c): array => [$c->id()->equals($second) ? $c->id()->toString() : 'new', $c->quantity()->value()],
                $found->components(),
            ),
        );

        self::assertNotNull($repo->findByCode(PackageCode::fromString('PKG-ZZCHIOT'), $clinicId));
        self::assertTrue($repo->existsByCode(PackageCode::fromString('PKG-ZZCHIOT'), $clinicId));
        self::assertNull($repo->findByCode(PackageCode::fromString('PKG-NONE'), $clinicId));
        self::assertNull($repo->findById(PackageId::fromString(Uuid::v7()->toRfc4122()), $clinicId));
    }

    private function repository(): PackageRepositoryInterface
    {
        $repository = self::getContainer()->get(PackageRepositoryInterface::class);
        \assert($repository instanceof PackageRepositoryInterface);

        return $repository;
    }

    private function entityManager(): EntityManagerInterface
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }
}
