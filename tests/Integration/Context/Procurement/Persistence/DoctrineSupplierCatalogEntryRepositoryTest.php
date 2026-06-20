<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Procurement\Persistence;

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
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryStatus;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductCode;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductName;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineSupplierCatalogEntryRepositoryTest extends KernelTestCase
{
    use Factories;

    private SupplierId $supplierId;

    protected function setUp(): void
    {
        self::bootKernel();

        // Persist a supplier that catalog entries will belong to
        $supplierRepo = self::getContainer()->get(SupplierRepositoryInterface::class);
        \assert($supplierRepo instanceof SupplierRepositoryInterface);

        $this->supplierId = SupplierId::fromString(Uuid::v7()->toString());
        $supplier         = Supplier::register(
            id: $this->supplierId,
            name: SupplierName::fromString('Catalog Supplier'),
            code: SupplierCode::fromString('CATSP-' . strtoupper(substr(Uuid::v7()->toString(), 0, 8))),
            type: SupplierType::CENTRALE,
            countryCode: CountryCode::fromString('FR'),
            defaultCurrency: CurrencyCode::fromString('EUR'),
            integrationMode: SupplierIntegrationMode::MANUAL_EXPORT,
            adapterIdentifier: null,
        );
        $_ = $supplier->pullDomainEvents();
        $supplierRepo->save($supplier);
    }

    public function testSaveAndFindById(): void
    {
        $repo = self::getContainer()->get(SupplierCatalogEntryRepositoryInterface::class);
        \assert($repo instanceof SupplierCatalogEntryRepositoryInterface);

        $entry = SupplierCatalogEntry::add(
            id: SupplierCatalogEntryId::fromString(Uuid::v7()->toString()),
            supplierId: $this->supplierId,
            productCode: SupplierProductCode::fromString('PROD-' . substr(Uuid::v7()->toString(), 0, 8)),
            name: SupplierProductName::fromString('Amoxicilline 500mg'),
            gtin: null,
            catalogPrice: CatalogPrice::create(
                Money::fromMinorUnits(2500, CurrencyCode::fromString('EUR')),
                new \DateTimeImmutable('2026-01-01'),
            ),
        );
        $_ = $entry->pullDomainEvents();

        $repo->save($entry);

        $found = $repo->findById($entry->id());
        self::assertNotNull($found);
        self::assertSame($entry->id()->toString(), $found->id()->toString());
        self::assertSame('Amoxicilline 500mg', $found->name()->toString());
        self::assertSame(2500, $found->catalogPrice()->amount->minorUnits());
        self::assertSame(SupplierCatalogEntryStatus::ACTIVE, $found->status());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $repo = self::getContainer()->get(SupplierCatalogEntryRepositoryInterface::class);
        \assert($repo instanceof SupplierCatalogEntryRepositoryInterface);

        $result = $repo->findById(SupplierCatalogEntryId::fromString('01932b00-0000-7fff-8000-000000000000'));
        self::assertNull($result);
    }

    public function testFindBySupplierAndCode(): void
    {
        $repo = self::getContainer()->get(SupplierCatalogEntryRepositoryInterface::class);
        \assert($repo instanceof SupplierCatalogEntryRepositoryInterface);

        $uniqueCode = 'PC-' . substr(Uuid::v7()->toString(), 0, 8);
        $entry      = SupplierCatalogEntry::add(
            id: SupplierCatalogEntryId::fromString(Uuid::v7()->toString()),
            supplierId: $this->supplierId,
            productCode: SupplierProductCode::fromString($uniqueCode),
            name: SupplierProductName::fromString('Vaccin Rage'),
            gtin: null,
            catalogPrice: CatalogPrice::create(
                Money::fromMinorUnits(3200, CurrencyCode::fromString('EUR')),
                new \DateTimeImmutable('2026-01-01'),
            ),
        );
        $_ = $entry->pullDomainEvents();

        $repo->save($entry);

        $found = $repo->findBySupplierAndCode($this->supplierId, SupplierProductCode::fromString($uniqueCode));
        self::assertNotNull($found);
        self::assertSame($entry->id()->toString(), $found->id()->toString());
    }

    public function testFindBySupplierAndCodeReturnsNullWhenNotFound(): void
    {
        $repo = self::getContainer()->get(SupplierCatalogEntryRepositoryInterface::class);
        \assert($repo instanceof SupplierCatalogEntryRepositoryInterface);

        $result = $repo->findBySupplierAndCode(
            $this->supplierId,
            SupplierProductCode::fromString('NONEXISTENT'),
        );
        self::assertNull($result);
    }
}
