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
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineSupplierRepositoryTest extends KernelTestCase
{
    use Factories;

    public function testSaveAndFindById(): void
    {
        $repo = self::getContainer()->get(SupplierRepositoryInterface::class);
        \assert($repo instanceof SupplierRepositoryInterface);

        $supplier = Supplier::register(
            id: SupplierId::fromString(Uuid::v7()->toString()),
            name: SupplierName::fromString('Centravet'),
            code: SupplierCode::fromString('CENTRAVET-' . strtoupper(substr(Uuid::v7()->toString(), 0, 8))),
            type: SupplierType::CENTRALE,
            countryCode: CountryCode::fromString('FR'),
            defaultCurrency: CurrencyCode::fromString('EUR'),
            integrationMode: SupplierIntegrationMode::SIMULATION,
            adapterIdentifier: 'simulated',
        );
        $_ = $supplier->pullDomainEvents();

        $repo->save($supplier);

        $found = $repo->findById($supplier->id());
        self::assertNotNull($found);
        self::assertSame($supplier->id()->toString(), $found->id()->toString());
        self::assertSame('Centravet', $found->name()->toString());
        self::assertSame(SupplierType::CENTRALE, $found->type());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $repo = self::getContainer()->get(SupplierRepositoryInterface::class);
        \assert($repo instanceof SupplierRepositoryInterface);

        $result = $repo->findById(SupplierId::fromString('01932b00-0000-7fff-8000-000000000000'));
        self::assertNull($result);
    }

    public function testFindByCodeReturnsNullWhenNotFound(): void
    {
        $repo = self::getContainer()->get(SupplierRepositoryInterface::class);
        \assert($repo instanceof SupplierRepositoryInterface);

        $result = $repo->findByCode(SupplierCode::fromString('NONEXISTENT'));
        self::assertNull($result);
    }

    public function testFindByCode(): void
    {
        $repo = self::getContainer()->get(SupplierRepositoryInterface::class);
        \assert($repo instanceof SupplierRepositoryInterface);

        $uniqueCode = 'ALCYON-' . strtoupper(substr(Uuid::v7()->toString(), 0, 8));
        $supplier   = Supplier::register(
            id: SupplierId::fromString(Uuid::v7()->toString()),
            name: SupplierName::fromString('Alcyon'),
            code: SupplierCode::fromString($uniqueCode),
            type: SupplierType::LABORATORY,
            countryCode: CountryCode::fromString('FR'),
            defaultCurrency: CurrencyCode::fromString('EUR'),
            integrationMode: SupplierIntegrationMode::MANUAL_EXPORT,
            adapterIdentifier: null,
        );
        $_ = $supplier->pullDomainEvents();

        $repo->save($supplier);

        $found = $repo->findByCode(SupplierCode::fromString($uniqueCode));
        self::assertNotNull($found);
        self::assertSame($supplier->id()->toString(), $found->id()->toString());
        self::assertSame('Alcyon', $found->name()->toString());
    }

    public function testFindAll(): void
    {
        $repo = self::getContainer()->get(SupplierRepositoryInterface::class);
        \assert($repo instanceof SupplierRepositoryInterface);

        $countBefore = \count($repo->findAll());

        $supplier1 = Supplier::register(
            id: SupplierId::fromString(Uuid::v7()->toString()),
            name: SupplierName::fromString('Supplier A'),
            code: SupplierCode::fromString('SUPPLA-' . strtoupper(substr(Uuid::v7()->toString(), 0, 8))),
            type: SupplierType::CENTRALE,
            countryCode: CountryCode::fromString('FR'),
            defaultCurrency: CurrencyCode::fromString('EUR'),
            integrationMode: SupplierIntegrationMode::MANUAL_EXPORT,
            adapterIdentifier: null,
        );
        $_ = $supplier1->pullDomainEvents();

        $supplier2 = Supplier::register(
            id: SupplierId::fromString(Uuid::v7()->toString()),
            name: SupplierName::fromString('Supplier B'),
            code: SupplierCode::fromString('SUPPLB-' . strtoupper(substr(Uuid::v7()->toString(), 0, 8))),
            type: SupplierType::LABORATORY,
            countryCode: CountryCode::fromString('FR'),
            defaultCurrency: CurrencyCode::fromString('EUR'),
            integrationMode: SupplierIntegrationMode::MANUAL_EXPORT,
            adapterIdentifier: null,
        );
        $_ = $supplier2->pullDomainEvents();

        $repo->save($supplier1);
        $repo->save($supplier2);

        $all = $repo->findAll();
        self::assertCount($countBefore + 2, $all);
    }
}
