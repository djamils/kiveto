<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Command;

use App\Context\Procurement\Application\Command\SubmitPurchaseOrder\SubmitPurchaseOrder;
use App\Context\Procurement\Application\Command\SubmitPurchaseOrder\SubmitPurchaseOrderHandler;
use App\Context\Procurement\Application\Dto\SendOrderResult;
use App\Context\Procurement\Application\Exception\SupplierAccountDisabledException;
use App\Context\Procurement\Application\Port\SupplierIntegrationAdapterInterface;
use App\Context\Procurement\Application\Service\SupplierIntegrationDispatcher;
use App\Context\Procurement\Domain\PurchaseOrder\Exception\PurchaseOrderNotFoundException;
use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Context\Procurement\Domain\PurchaseOrder\Repository\PurchaseOrderRepositoryInterface;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\ExternalReference;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderNumber;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderStatus;
use App\Context\Procurement\Domain\Shared\ValueObject\Address;
use App\Context\Procurement\Domain\Shared\ValueObject\ArticleId;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\Exception\SupplierNotFoundException;
use App\Context\Procurement\Domain\Supplier\Repository\SupplierRepositoryInterface;
use App\Context\Procurement\Domain\Supplier\Supplier;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierCode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierIntegrationMode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierName;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierType;
use App\Context\Procurement\Domain\SupplierAccount\Exception\SupplierAccountNotFoundException;
use App\Context\Procurement\Domain\SupplierAccount\Repository\SupplierAccountRepositoryInterface;
use App\Context\Procurement\Domain\SupplierAccount\SupplierAccount;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\CustomerCode;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class SubmitPurchaseOrderHandlerTest extends TestCase
{
    private const string PO_UUID       = '01932b00-0000-7000-8000-000000000100';
    private const string LINE_UUID     = '01932b00-0000-7000-8000-000000000101';
    private const string CLINIC_UUID   = '01932b00-0000-7000-8000-000000000003';
    private const string SUPPLIER_UUID = '01932b00-0000-7000-8000-000000000001';
    private const string ACCOUNT_UUID  = '01932b00-0000-7000-8000-000000000002';
    private const string ARTICLE_UUID  = '01932b00-0000-7000-8000-000000000200';
    private const string CATALOG_UUID  = '01932b00-0000-7000-8000-000000000300';

    public function testItTransitionsToSubmittedOnAdapterSuccess(): void
    {
        $po = $this->makePurchaseOrderWithLine();

        $repository = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $repository->method('findById')->willReturn($po);

        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findById')->willReturn($this->makeSupplier());

        $accountRepo = $this->createStub(SupplierAccountRepositoryInterface::class);
        $accountRepo->method('findById')->willReturn($this->makeActiveAccount());

        $adapter = $this->makeSimAdapter();
        $adapter->method('sendOrder')->willReturn(SendOrderResult::success(
            ref: ExternalReference::create('EXT-001', 'test-adapter'),
            sentAt: new \DateTimeImmutable(),
        ));

        $eventBus = $this->createStub(EventBusInterface::class);

        ($this->makeHandler($repository, $supplierRepo, $accountRepo, $adapter, $eventBus))(
            new SubmitPurchaseOrder(purchaseOrderId: self::PO_UUID, clinicId: self::CLINIC_UUID)
        );

        self::assertSame(PurchaseOrderStatus::SUBMITTED, $po->status());
    }

    public function testItTransitionsToSendFailedOnAdapterFailureWithoutRethrowing(): void
    {
        $po = $this->makePurchaseOrderWithLine();

        $repository = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $repository->method('findById')->willReturn($po);

        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findById')->willReturn($this->makeSupplier());

        $accountRepo = $this->createStub(SupplierAccountRepositoryInterface::class);
        $accountRepo->method('findById')->willReturn($this->makeActiveAccount());

        $adapter = $this->makeSimAdapter();
        $adapter->method('sendOrder')->willReturn(SendOrderResult::failure(
            errorMessage: 'Network timeout',
            sentAt: new \DateTimeImmutable(),
        ));

        $eventBus = $this->createStub(EventBusInterface::class);

        // Must NOT throw even though sending failed
        ($this->makeHandler($repository, $supplierRepo, $accountRepo, $adapter, $eventBus))(
            new SubmitPurchaseOrder(purchaseOrderId: self::PO_UUID, clinicId: self::CLINIC_UUID)
        );

        self::assertSame(PurchaseOrderStatus::SEND_FAILED, $po->status());
    }

    public function testItThrowsWhenSupplierAccountIsDisabled(): void
    {
        $po = $this->makePurchaseOrderWithLine();

        $repository = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $repository->method('findById')->willReturn($po);

        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findById')->willReturn($this->makeSupplier());

        $accountRepo = $this->createStub(SupplierAccountRepositoryInterface::class);
        $accountRepo->method('findById')->willReturn($this->makeDisabledAccount());

        $adapter  = $this->makeSimAdapter();
        $eventBus = $this->createStub(EventBusInterface::class);

        $this->expectException(SupplierAccountDisabledException::class);

        ($this->makeHandler($repository, $supplierRepo, $accountRepo, $adapter, $eventBus))(
            new SubmitPurchaseOrder(purchaseOrderId: self::PO_UUID, clinicId: self::CLINIC_UUID)
        );
    }

    public function testItPublishesEventsTwice(): void
    {
        $po = $this->makePurchaseOrderWithLine();

        $repository = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $repository->method('findById')->willReturn($po);

        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findById')->willReturn($this->makeSupplier());

        $accountRepo = $this->createStub(SupplierAccountRepositoryInterface::class);
        $accountRepo->method('findById')->willReturn($this->makeActiveAccount());

        $adapter = $this->makeSimAdapter();
        $adapter->method('sendOrder')->willReturn(SendOrderResult::success(
            ref: ExternalReference::create('EXT-001', 'test-adapter'),
            sentAt: new \DateTimeImmutable(),
        ));

        // EventBus publish called twice: once after markAsSubmitting, once after submit
        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::exactly(2))->method('publish');

        ($this->makeHandler($repository, $supplierRepo, $accountRepo, $adapter, $eventBus))(
            new SubmitPurchaseOrder(purchaseOrderId: self::PO_UUID, clinicId: self::CLINIC_UUID)
        );
    }

    public function testItThrowsWhenPurchaseOrderNotFound(): void
    {
        $repository = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $accountRepo  = $this->createStub(SupplierAccountRepositoryInterface::class);
        $adapter      = $this->makeSimAdapter();
        $eventBus     = $this->createStub(EventBusInterface::class);

        $this->expectException(PurchaseOrderNotFoundException::class);

        ($this->makeHandler($repository, $supplierRepo, $accountRepo, $adapter, $eventBus))(
            new SubmitPurchaseOrder(purchaseOrderId: self::PO_UUID, clinicId: self::CLINIC_UUID)
        );
    }

    public function testItThrowsWhenSupplierNotFound(): void
    {
        $po = $this->makePurchaseOrderWithLine();

        $repository = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $repository->method('findById')->willReturn($po);

        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findById')->willReturn(null);

        $accountRepo = $this->createStub(SupplierAccountRepositoryInterface::class);
        $adapter     = $this->makeSimAdapter();
        $eventBus    = $this->createStub(EventBusInterface::class);

        $this->expectException(SupplierNotFoundException::class);

        ($this->makeHandler($repository, $supplierRepo, $accountRepo, $adapter, $eventBus))(
            new SubmitPurchaseOrder(purchaseOrderId: self::PO_UUID, clinicId: self::CLINIC_UUID)
        );
    }

    public function testItThrowsWhenSupplierAccountNotFound(): void
    {
        $po = $this->makePurchaseOrderWithLine();

        $repository = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $repository->method('findById')->willReturn($po);

        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findById')->willReturn($this->makeSupplier());

        $accountRepo = $this->createStub(SupplierAccountRepositoryInterface::class);
        $accountRepo->method('findById')->willReturn(null);

        $adapter  = $this->makeSimAdapter();
        $eventBus = $this->createStub(EventBusInterface::class);

        $this->expectException(SupplierAccountNotFoundException::class);

        ($this->makeHandler($repository, $supplierRepo, $accountRepo, $adapter, $eventBus))(
            new SubmitPurchaseOrder(purchaseOrderId: self::PO_UUID, clinicId: self::CLINIC_UUID)
        );
    }

    private function makeHandler(
        PurchaseOrderRepositoryInterface $repository,
        SupplierRepositoryInterface $supplierRepo,
        SupplierAccountRepositoryInterface $accountRepo,
        SupplierIntegrationAdapterInterface $adapter,
        EventBusInterface $eventBus,
    ): SubmitPurchaseOrderHandler {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-15'));

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(static function (callable $fn): void {
            $fn();
        });

        return new SubmitPurchaseOrderHandler(
            $repository,
            $supplierRepo,
            $accountRepo,
            new SupplierIntegrationDispatcher([$adapter]),
            new DomainEventPublisher($eventBus),
            $clock,
            $em,
        );
    }

    /**
     * @return \PHPUnit\Framework\MockObject\Stub&SupplierIntegrationAdapterInterface
     */
    private function makeSimAdapter(): SupplierIntegrationAdapterInterface
    {
        $adapter = $this->createStub(SupplierIntegrationAdapterInterface::class);
        $adapter->method('identifier')->willReturn('sim');
        $adapter->method('mode')->willReturn(SupplierIntegrationMode::SIMULATION);

        return $adapter;
    }

    private function makePurchaseOrderWithLine(): PurchaseOrder
    {
        $po = PurchaseOrder::create(
            id: PurchaseOrderId::fromString(self::PO_UUID),
            clinicId: ClinicId::fromString(self::CLINIC_UUID),
            supplierId: SupplierId::fromString(self::SUPPLIER_UUID),
            supplierAccountId: SupplierAccountId::fromString(self::ACCOUNT_UUID),
            orderNumber: PurchaseOrderNumber::fromString('PO-2026-000001'),
            currency: CurrencyCode::fromString('EUR'),
            deliveryAddress: Address::create(null, null, null, null, null),
        );
        $_ = $po->pullDomainEvents();

        $po->addLine(
            lineId: PurchaseOrderLineId::fromString(self::LINE_UUID),
            articleId: ArticleId::fromString(self::ARTICLE_UUID),
            catalogEntryId: SupplierCatalogEntryId::fromString(self::CATALOG_UUID),
            orderedAmount: '5',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: Money::fromMinorUnits(1000, CurrencyCode::fromString('EUR')),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );
        $_ = $po->pullDomainEvents();

        return $po;
    }

    private function makeSupplier(): Supplier
    {
        return Supplier::register(
            id: SupplierId::fromString(self::SUPPLIER_UUID),
            name: SupplierName::fromString('Centravet'),
            code: SupplierCode::fromString('CENTRAVET'),
            type: SupplierType::CENTRALE,
            countryCode: CountryCode::fromString('FR'),
            defaultCurrency: CurrencyCode::fromString('EUR'),
            integrationMode: SupplierIntegrationMode::SIMULATION,
            adapterIdentifier: null,
        );
    }

    private function makeActiveAccount(): SupplierAccount
    {
        return SupplierAccount::create(
            id: SupplierAccountId::fromString(self::ACCOUNT_UUID),
            clinicId: ClinicId::fromString(self::CLINIC_UUID),
            supplierId: SupplierId::fromString(self::SUPPLIER_UUID),
            customerCode: CustomerCode::fromString('CVT-12345'),
        );
    }

    private function makeDisabledAccount(): SupplierAccount
    {
        $account = SupplierAccount::create(
            id: SupplierAccountId::fromString(self::ACCOUNT_UUID),
            clinicId: ClinicId::fromString(self::CLINIC_UUID),
            supplierId: SupplierId::fromString(self::SUPPLIER_UUID),
            customerCode: CustomerCode::fromString('CVT-12345'),
        );
        $_ = $account->pullDomainEvents();
        $account->disable(new \DateTimeImmutable());
        $_ = $account->pullDomainEvents();

        return $account;
    }
}
