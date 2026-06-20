<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Procurement\Persistence;

use App\Context\Procurement\Domain\PurchaseOrder\Exception\ConcurrentModificationException;
use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Context\Procurement\Domain\PurchaseOrder\Repository\PurchaseOrderRepositoryInterface;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderNumber;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderStatus;
use App\Context\Procurement\Domain\Shared\ValueObject\Address;
use App\Context\Procurement\Domain\Shared\ValueObject\ArticleId;
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
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Mapper\PurchaseOrderMapper;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Repository\DoctrinePurchaseOrderRepository;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrinePurchaseOrderRepositoryTest extends KernelTestCase
{
    use Factories;

    private SupplierId $supplierId;
    private SupplierAccountId $accountId;
    private ClinicId $clinicId;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->clinicId = ClinicId::fromString(Uuid::v7()->toString());

        // Persist a supplier
        $supplierRepo = self::getContainer()->get(SupplierRepositoryInterface::class);
        \assert($supplierRepo instanceof SupplierRepositoryInterface);
        $this->supplierId = SupplierId::fromString(Uuid::v7()->toString());
        $supplier         = Supplier::register(
            id: $this->supplierId,
            name: SupplierName::fromString('PO Supplier'),
            code: SupplierCode::fromString('POSUP-' . strtoupper(substr(Uuid::v7()->toString(), 0, 8))),
            type: SupplierType::CENTRALE,
            countryCode: CountryCode::fromString('FR'),
            defaultCurrency: CurrencyCode::fromString('EUR'),
            integrationMode: SupplierIntegrationMode::MANUAL_EXPORT,
            adapterIdentifier: null,
        );
        $_ = $supplier->pullDomainEvents();
        $supplierRepo->save($supplier);

        // Persist a supplier account
        $accountRepo = self::getContainer()->get(SupplierAccountRepositoryInterface::class);
        \assert($accountRepo instanceof SupplierAccountRepositoryInterface);
        $this->accountId = SupplierAccountId::fromString(Uuid::v7()->toString());
        $account         = SupplierAccount::create(
            id: $this->accountId,
            clinicId: $this->clinicId,
            supplierId: $this->supplierId,
            customerCode: CustomerCode::fromString('PO-CUST'),
        );
        $_ = $account->pullDomainEvents();
        $accountRepo->save($account);
    }

    public function testSaveAndFindById(): void
    {
        $repo = self::getContainer()->get(PurchaseOrderRepositoryInterface::class);
        \assert($repo instanceof PurchaseOrderRepositoryInterface);

        $order = PurchaseOrder::create(
            id: PurchaseOrderId::fromString(Uuid::v7()->toString()),
            clinicId: $this->clinicId,
            supplierId: $this->supplierId,
            supplierAccountId: $this->accountId,
            orderNumber: PurchaseOrderNumber::fromString('PO-2026-000001'),
            currency: CurrencyCode::fromString('EUR'),
            deliveryAddress: Address::create('5 rue du Test', null, '75000', 'Paris', null),
        );
        $_ = $order->pullDomainEvents();

        $repo->save($order);

        $found = $repo->findById($order->id());
        self::assertNotNull($found);
        self::assertSame($order->id()->toString(), $found->id()->toString());
        self::assertSame($this->clinicId->toString(), $found->clinicId()->toString());
        self::assertSame('PO-2026-000001', $found->orderNumber()->toString());
        self::assertSame(PurchaseOrderStatus::DRAFT, $found->status());
        self::assertSame('5 rue du Test', $found->deliveryAddress()->street);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $repo = self::getContainer()->get(PurchaseOrderRepositoryInterface::class);
        \assert($repo instanceof PurchaseOrderRepositoryInterface);

        $result = $repo->findById(PurchaseOrderId::fromString('01932b00-0000-7fff-8000-000000000000'));
        self::assertNull($result);
    }

    public function testFindByClinicAndNumber(): void
    {
        $repo = self::getContainer()->get(PurchaseOrderRepositoryInterface::class);
        \assert($repo instanceof PurchaseOrderRepositoryInterface);

        $orderNumber = PurchaseOrderNumber::fromString('PO-2026-000042');
        $order       = PurchaseOrder::create(
            id: PurchaseOrderId::fromString(Uuid::v7()->toString()),
            clinicId: $this->clinicId,
            supplierId: $this->supplierId,
            supplierAccountId: $this->accountId,
            orderNumber: $orderNumber,
            currency: CurrencyCode::fromString('EUR'),
            deliveryAddress: Address::create(null, null, null, null, null),
        );
        $_ = $order->pullDomainEvents();

        $repo->save($order);

        $found = $repo->findByClinicAndNumber($this->clinicId, $orderNumber);
        self::assertNotNull($found);
        self::assertSame($order->id()->toString(), $found->id()->toString());
    }

    public function testSaveAddsAndRemovesLinesOnExistingPurchaseOrder(): void
    {
        $repo = self::getContainer()->get(PurchaseOrderRepositoryInterface::class);
        \assert($repo instanceof PurchaseOrderRepositoryInterface);

        $articleId      = ArticleId::fromString(Uuid::v7()->toString());
        $catalogEntryId = SupplierCatalogEntryId::fromString(Uuid::v7()->toString());

        $orderId = PurchaseOrderId::fromString(Uuid::v7()->toString());
        $order   = PurchaseOrder::create(
            id: $orderId,
            clinicId: $this->clinicId,
            supplierId: $this->supplierId,
            supplierAccountId: $this->accountId,
            orderNumber: PurchaseOrderNumber::fromString('PO-2026-000123'),
            currency: CurrencyCode::fromString('EUR'),
            deliveryAddress: Address::create(null, null, null, null, null),
        );
        $lineToKeepId = PurchaseOrderLineId::fromString(Uuid::v7()->toString());
        $order->addLine(
            lineId: $lineToKeepId,
            articleId: $articleId,
            catalogEntryId: $catalogEntryId,
            orderedAmount: '5',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: Money::fromMinorUnits(1000, CurrencyCode::fromString('EUR')),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );
        $lineToRemoveId = PurchaseOrderLineId::fromString(Uuid::v7()->toString());
        $order->addLine(
            lineId: $lineToRemoveId,
            articleId: $articleId,
            catalogEntryId: $catalogEntryId,
            orderedAmount: '3',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: Money::fromMinorUnits(500, CurrencyCode::fromString('EUR')),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );
        $_ = $order->pullDomainEvents();
        $repo->save($order);

        // Now fetch back, ADD a new line and REMOVE one — exercising the L64 (add) and L70-71 (orphan removal) branches
        $reloaded = $repo->findById($orderId);
        self::assertNotNull($reloaded);

        $newLineId = PurchaseOrderLineId::fromString(Uuid::v7()->toString());
        $reloaded->addLine(
            lineId: $newLineId,
            articleId: $articleId,
            catalogEntryId: $catalogEntryId,
            orderedAmount: '2',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: Money::fromMinorUnits(200, CurrencyCode::fromString('EUR')),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );
        $reloaded->removeLine($lineToRemoveId, new \DateTimeImmutable());
        $_ = $reloaded->pullDomainEvents();

        $repo->save($reloaded);

        $final = $repo->findById($orderId);
        self::assertNotNull($final);
        self::assertCount(2, $final->lines());
        $finalIds = array_map(static fn ($l) => $l->id()->toString(), $final->lines());
        self::assertContains($lineToKeepId->toString(), $finalIds);
        self::assertContains($newLineId->toString(), $finalIds);
        self::assertNotContains($lineToRemoveId->toString(), $finalIds);
    }

    public function testSaveThrowsConcurrentModificationExceptionOnOptimisticLockFailure(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturn(null);
        $em->method('flush')->willThrowException(new OptimisticLockException('stale', null));

        $repo = new DoctrinePurchaseOrderRepository($em, new PurchaseOrderMapper());

        $order = PurchaseOrder::create(
            id: PurchaseOrderId::fromString(Uuid::v7()->toString()),
            clinicId: $this->clinicId,
            supplierId: $this->supplierId,
            supplierAccountId: $this->accountId,
            orderNumber: PurchaseOrderNumber::fromString('PO-2026-000777'),
            currency: CurrencyCode::fromString('EUR'),
            deliveryAddress: Address::create(null, null, null, null, null),
        );
        $_ = $order->pullDomainEvents();

        $this->expectException(ConcurrentModificationException::class);
        $repo->save($order);
    }

    public function testVersionColumnIsInitializedToOne(): void
    {
        $repo = self::getContainer()->get(PurchaseOrderRepositoryInterface::class);
        \assert($repo instanceof PurchaseOrderRepositoryInterface);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        $orderId = PurchaseOrderId::fromString(Uuid::v7()->toString());
        $order   = PurchaseOrder::create(
            id: $orderId,
            clinicId: $this->clinicId,
            supplierId: $this->supplierId,
            supplierAccountId: $this->accountId,
            orderNumber: PurchaseOrderNumber::fromString('PO-2026-000099'),
            currency: CurrencyCode::fromString('EUR'),
            deliveryAddress: Address::create(null, null, null, null, null),
        );
        $_ = $order->pullDomainEvents();
        $repo->save($order);

        // Verify the initial DB version is 1 (ORM\Version field default)
        $versionAfterInsert = $em->getConnection()->fetchOne(
            'SELECT version FROM procurement__purchase_orders WHERE id = :id',
            ['id' => Uuid::fromString($orderId->toString())],
            ['id' => UuidType::NAME],
        );
        \assert(\is_string($versionAfterInsert) || \is_int($versionAfterInsert));
        self::assertSame(1, (int) $versionAfterInsert);
    }
}
