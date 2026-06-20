<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Procurement\Persistence;

use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\Repository\SupplierRepositoryInterface;
use App\Context\Procurement\Domain\Supplier\Supplier;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierCode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierIntegrationMode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierName;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierType;
use App\Context\Procurement\Domain\SupplierCatalog\Repository\SupplierCatalogEntryRepositoryInterface;
use App\Context\Procurement\Domain\SupplierCatalog\SupplierCatalogEntry;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\CatalogPrice;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductCode;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductName;
use App\Context\Procurement\Domain\SupplierPricing\Repository\SupplierPricingRepositoryInterface;
use App\Context\Procurement\Domain\SupplierPricing\SupplierPricing;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\NegotiatedPrice;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\SupplierPricingId;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineSupplierPricingRepositoryTest extends KernelTestCase
{
    use Factories;

    private SupplierId $supplierId;
    private SupplierCatalogEntryId $catalogEntryId;

    protected function setUp(): void
    {
        self::bootKernel();

        // Persist a supplier
        $supplierRepo = self::getContainer()->get(SupplierRepositoryInterface::class);
        \assert($supplierRepo instanceof SupplierRepositoryInterface);

        $this->supplierId = SupplierId::fromString(Uuid::v7()->toString());
        $supplier         = Supplier::register(
            id: $this->supplierId,
            name: SupplierName::fromString('Pricing Supplier'),
            code: SupplierCode::fromString('PRSUP-' . strtoupper(substr(Uuid::v7()->toString(), 0, 8))),
            type: SupplierType::CENTRALE,
            countryCode: CountryCode::fromString('FR'),
            defaultCurrency: CurrencyCode::fromString('EUR'),
            integrationMode: SupplierIntegrationMode::MANUAL_EXPORT,
            adapterIdentifier: null,
        );
        $_ = $supplier->pullDomainEvents();
        $supplierRepo->save($supplier);

        // Persist a catalog entry
        $catalogRepo = self::getContainer()->get(SupplierCatalogEntryRepositoryInterface::class);
        \assert($catalogRepo instanceof SupplierCatalogEntryRepositoryInterface);

        $this->catalogEntryId = SupplierCatalogEntryId::fromString(Uuid::v7()->toString());
        $entry                = SupplierCatalogEntry::add(
            id: $this->catalogEntryId,
            supplierId: $this->supplierId,
            productCode: SupplierProductCode::fromString('PRPROD-' . substr(Uuid::v7()->toString(), 0, 8)),
            name: SupplierProductName::fromString('Amoxicilline'),
            gtin: null,
            catalogPrice: CatalogPrice::create(
                Money::fromMinorUnits(2500, CurrencyCode::fromString('EUR')),
                new \DateTimeImmutable('2026-01-01'),
            ),
        );
        $_ = $entry->pullDomainEvents();
        $catalogRepo->save($entry);
    }

    public function testSaveAndFindById(): void
    {
        $repo = self::getContainer()->get(SupplierPricingRepositoryInterface::class);
        \assert($repo instanceof SupplierPricingRepositoryInterface);

        $clinicId     = ClinicId::fromString(Uuid::v7()->toString());
        $negotiatedAt = new \DateTimeImmutable('2026-01-10');

        $pricing = SupplierPricing::negotiate(
            id: SupplierPricingId::fromString(Uuid::v7()->toString()),
            clinicId: $clinicId,
            supplierId: $this->supplierId,
            catalogEntryId: $this->catalogEntryId,
            negotiatedPrice: NegotiatedPrice::create(
                Money::fromMinorUnits(2000, CurrencyCode::fromString('EUR')),
                '10.00',
                'Volume discount',
            ),
            expiresAt: new \DateTimeImmutable('2026-12-31'),
            negotiatedAt: $negotiatedAt,
        );
        $_ = $pricing->pullDomainEvents();

        $repo->save($pricing);

        $found = $repo->findById($pricing->id());
        self::assertNotNull($found);
        self::assertSame($pricing->id()->toString(), $found->id()->toString());
        self::assertSame(2000, $found->negotiatedPrice()->amount->minorUnits());
        self::assertSame('10.00', $found->negotiatedPrice()->discountPercentage);
        self::assertSame('Volume discount', $found->negotiatedPrice()->notes);
        self::assertNotNull($found->expiresAt());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $repo = self::getContainer()->get(SupplierPricingRepositoryInterface::class);
        \assert($repo instanceof SupplierPricingRepositoryInterface);

        $result = $repo->findById(SupplierPricingId::fromString('01932b00-0000-7fff-8000-000000000000'));
        self::assertNull($result);
    }

    public function testFindByClinicAndEntry(): void
    {
        $repo = self::getContainer()->get(SupplierPricingRepositoryInterface::class);
        \assert($repo instanceof SupplierPricingRepositoryInterface);

        $clinicId     = ClinicId::fromString(Uuid::v7()->toString());
        $negotiatedAt = new \DateTimeImmutable('2026-01-10');

        $pricing = SupplierPricing::negotiate(
            id: SupplierPricingId::fromString(Uuid::v7()->toString()),
            clinicId: $clinicId,
            supplierId: $this->supplierId,
            catalogEntryId: $this->catalogEntryId,
            negotiatedPrice: NegotiatedPrice::create(
                Money::fromMinorUnits(1800, CurrencyCode::fromString('EUR')),
            ),
            expiresAt: null,
            negotiatedAt: $negotiatedAt,
        );
        $_ = $pricing->pullDomainEvents();

        $repo->save($pricing);

        $found = $repo->findByClinicAndEntry($clinicId, $this->catalogEntryId);
        self::assertNotNull($found);
        self::assertSame($pricing->id()->toString(), $found->id()->toString());
    }

    public function testFindByClinicAndEntryReturnsNullWhenNotFound(): void
    {
        $repo = self::getContainer()->get(SupplierPricingRepositoryInterface::class);
        \assert($repo instanceof SupplierPricingRepositoryInterface);

        $result = $repo->findByClinicAndEntry(
            ClinicId::fromString(Uuid::v7()->toString()),
            $this->catalogEntryId,
        );
        self::assertNull($result);
    }

    public function testRemoveDeletesExistingPricing(): void
    {
        $repo = self::getContainer()->get(SupplierPricingRepositoryInterface::class);
        \assert($repo instanceof SupplierPricingRepositoryInterface);

        $pricing = SupplierPricing::negotiate(
            id: SupplierPricingId::fromString(Uuid::v7()->toString()),
            clinicId: ClinicId::fromString(Uuid::v7()->toString()),
            supplierId: $this->supplierId,
            catalogEntryId: $this->catalogEntryId,
            negotiatedPrice: NegotiatedPrice::create(
                Money::fromMinorUnits(900, CurrencyCode::fromString('EUR')),
            ),
            expiresAt: null,
            negotiatedAt: new \DateTimeImmutable(),
        );
        $_ = $pricing->pullDomainEvents();
        $repo->save($pricing);

        // Sanity check
        self::assertNotNull($repo->findById($pricing->id()));

        $repo->remove($pricing);

        self::assertNull($repo->findById($pricing->id()));
    }

    public function testRemoveDoesNothingWhenPricingNotFound(): void
    {
        $repo = self::getContainer()->get(SupplierPricingRepositoryInterface::class);
        \assert($repo instanceof SupplierPricingRepositoryInterface);

        // Build a never-persisted SupplierPricing
        $pricing = SupplierPricing::negotiate(
            id: SupplierPricingId::fromString(Uuid::v7()->toString()),
            clinicId: ClinicId::fromString(Uuid::v7()->toString()),
            supplierId: $this->supplierId,
            catalogEntryId: $this->catalogEntryId,
            negotiatedPrice: NegotiatedPrice::create(
                Money::fromMinorUnits(500, CurrencyCode::fromString('EUR')),
            ),
            expiresAt: null,
            negotiatedAt: new \DateTimeImmutable(),
        );

        // Should be a no-op (does not throw)
        $repo->remove($pricing);

        self::assertNull($repo->findById($pricing->id()));
    }
}
