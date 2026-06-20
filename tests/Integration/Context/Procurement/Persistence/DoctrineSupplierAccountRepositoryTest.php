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
use App\Context\Procurement\Domain\SupplierAccount\Repository\SupplierAccountRepositoryInterface;
use App\Context\Procurement\Domain\SupplierAccount\SupplierAccount;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\CustomerCode;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountStatus;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineSupplierAccountRepositoryTest extends KernelTestCase
{
    use Factories;

    private SupplierId $supplierId;

    protected function setUp(): void
    {
        self::bootKernel();

        // Persist a supplier that accounts will be linked to
        $supplierRepo = self::getContainer()->get(SupplierRepositoryInterface::class);
        \assert($supplierRepo instanceof SupplierRepositoryInterface);

        $this->supplierId = SupplierId::fromString(Uuid::v7()->toString());
        $supplier         = Supplier::register(
            id: $this->supplierId,
            name: SupplierName::fromString('Test Supplier'),
            code: SupplierCode::fromString('TSUP-' . strtoupper(substr(Uuid::v7()->toString(), 0, 8))),
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
        $repo = self::getContainer()->get(SupplierAccountRepositoryInterface::class);
        \assert($repo instanceof SupplierAccountRepositoryInterface);

        $clinicId = ClinicId::fromString(Uuid::v7()->toString());
        $account  = SupplierAccount::create(
            id: SupplierAccountId::fromString(Uuid::v7()->toString()),
            clinicId: $clinicId,
            supplierId: $this->supplierId,
            customerCode: CustomerCode::fromString('CUST-001'),
            notes: 'Integration test account',
        );
        $_ = $account->pullDomainEvents();

        $repo->save($account);

        $found = $repo->findById($account->id());
        self::assertNotNull($found);
        self::assertSame($account->id()->toString(), $found->id()->toString());
        self::assertSame($clinicId->toString(), $found->clinicId()->toString());
        self::assertSame($this->supplierId->toString(), $found->supplierId()->toString());
        self::assertSame('CUST-001', $found->customerCode()->toString());
        self::assertSame(SupplierAccountStatus::ACTIVE, $found->status());
        self::assertSame('Integration test account', $found->notes());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $repo = self::getContainer()->get(SupplierAccountRepositoryInterface::class);
        \assert($repo instanceof SupplierAccountRepositoryInterface);

        $result = $repo->findById(SupplierAccountId::fromString('01932b00-0000-7fff-8000-000000000000'));
        self::assertNull($result);
    }

    public function testFindByClinicAndSupplier(): void
    {
        $repo = self::getContainer()->get(SupplierAccountRepositoryInterface::class);
        \assert($repo instanceof SupplierAccountRepositoryInterface);

        $clinicId = ClinicId::fromString(Uuid::v7()->toString());
        $account  = SupplierAccount::create(
            id: SupplierAccountId::fromString(Uuid::v7()->toString()),
            clinicId: $clinicId,
            supplierId: $this->supplierId,
            customerCode: CustomerCode::fromString('CUST-002'),
        );
        $_ = $account->pullDomainEvents();

        $repo->save($account);

        $found = $repo->findByClinicAndSupplier($clinicId, $this->supplierId);
        self::assertNotNull($found);
        self::assertSame($account->id()->toString(), $found->id()->toString());
    }

    public function testFindByClinicAndSupplierReturnsNullWhenNotFound(): void
    {
        $repo = self::getContainer()->get(SupplierAccountRepositoryInterface::class);
        \assert($repo instanceof SupplierAccountRepositoryInterface);

        $result = $repo->findByClinicAndSupplier(
            ClinicId::fromString(Uuid::v7()->toString()),
            $this->supplierId,
        );
        self::assertNull($result);
    }
}
