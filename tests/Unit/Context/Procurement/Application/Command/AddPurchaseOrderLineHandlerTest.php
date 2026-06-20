<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Command;

use App\Context\Procurement\Application\Command\AddPurchaseOrderLine\AddPurchaseOrderLine;
use App\Context\Procurement\Application\Command\AddPurchaseOrderLine\AddPurchaseOrderLineHandler;
use App\Context\Procurement\Application\Exception\DiscontinuedCatalogEntryException;
use App\Context\Procurement\Application\Port\SupplierCatalogReadRepositoryInterface;
use App\Context\Procurement\Domain\PurchaseOrder\Exception\PurchaseOrderNotFoundException;
use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Context\Procurement\Domain\PurchaseOrder\Repository\PurchaseOrderRepositoryInterface;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderNumber;
use App\Context\Procurement\Domain\Shared\ValueObject\Address;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class AddPurchaseOrderLineHandlerTest extends TestCase
{
    private const string PO_UUID          = '01932b00-0000-7000-8000-000000000100';
    private const string LINE_UUID        = '01932b00-0000-7000-8000-000000000101';
    private const string CLINIC_UUID      = '01932b00-0000-7000-8000-000000000003';
    private const string SUPPLIER_UUID    = '01932b00-0000-7000-8000-000000000001';
    private const string ACCOUNT_UUID     = '01932b00-0000-7000-8000-000000000002';
    private const string ARTICLE_UUID     = '01932b00-0000-7000-8000-000000000200';
    private const string CATALOG_ENTRY_ID = '01932b00-0000-7000-8000-000000000300';

    public function testItAddsLineToExistingDraftPurchaseOrder(): void
    {
        $po = $this->makeDraftPurchaseOrder();

        $repository = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $repository->method('findById')->willReturn($po);
        $repository->expects(self::once())->method('save');

        $catalogRepo = $this->createStub(SupplierCatalogReadRepositoryInterface::class);
        $catalogRepo->method('findById')->willReturn(['status' => 'ACTIVE']);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        ($this->makeHandler($repository, $catalogRepo, $eventBus))($this->makeCommand());
    }

    public function testItThrowsWhenPurchaseOrderNotFound(): void
    {
        $repository = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $catalogRepo = $this->createStub(SupplierCatalogReadRepositoryInterface::class);
        $eventBus    = $this->createStub(EventBusInterface::class);

        $this->expectException(PurchaseOrderNotFoundException::class);

        ($this->makeHandler($repository, $catalogRepo, $eventBus))($this->makeCommand());
    }

    public function testItThrowsWhenCatalogEntryIsDiscontinued(): void
    {
        $po = $this->makeDraftPurchaseOrder();

        $repository = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $repository->method('findById')->willReturn($po);

        $catalogRepo = $this->createStub(SupplierCatalogReadRepositoryInterface::class);
        $catalogRepo->method('findById')->willReturn(['status' => 'DISCONTINUED']);

        $eventBus = $this->createStub(EventBusInterface::class);

        $this->expectException(DiscontinuedCatalogEntryException::class);

        ($this->makeHandler($repository, $catalogRepo, $eventBus))($this->makeCommand());
    }

    public function testItThrowsWhenOrderedAmountIsNotNumeric(): void
    {
        $po = $this->makeDraftPurchaseOrder();

        $repository = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $repository->method('findById')->willReturn($po);

        $catalogRepo = $this->createStub(SupplierCatalogReadRepositoryInterface::class);
        $catalogRepo->method('findById')->willReturn(['status' => 'ACTIVE']);

        $eventBus = $this->createStub(EventBusInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('orderedAmount "abc" is not numeric.');

        ($this->makeHandler($repository, $catalogRepo, $eventBus))(new AddPurchaseOrderLine(
            purchaseOrderId: self::PO_UUID,
            clinicId: self::CLINIC_UUID,
            articleId: self::ARTICLE_UUID,
            catalogEntryId: self::CATALOG_ENTRY_ID,
            orderedAmount: 'abc',
            orderedUnit: 'UNIT',
            unitPriceMinor: 1000,
            unitPriceCurrency: 'EUR',
            note: null,
        ));
    }

    public function testItDoesNotThrowWhenCatalogEntryIsNull(): void
    {
        $po = $this->makeDraftPurchaseOrder();

        $repository = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $repository->method('findById')->willReturn($po);
        $repository->expects(self::once())->method('save');

        // Catalog entry not found — should not throw
        $catalogRepo = $this->createStub(SupplierCatalogReadRepositoryInterface::class);
        $catalogRepo->method('findById')->willReturn(null);

        $eventBus = $this->createStub(EventBusInterface::class);

        ($this->makeHandler($repository, $catalogRepo, $eventBus))($this->makeCommand());
    }

    private function makeHandler(
        PurchaseOrderRepositoryInterface $repository,
        SupplierCatalogReadRepositoryInterface $catalogRepo,
        EventBusInterface $eventBus,
    ): AddPurchaseOrderLineHandler {
        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturn(self::LINE_UUID);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-15'));

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(static function (callable $fn): void {
            $fn();
        });

        return new AddPurchaseOrderLineHandler(
            $repository,
            $catalogRepo,
            new DomainEventPublisher($eventBus),
            $uuidGenerator,
            $clock,
            $em,
        );
    }

    private function makeCommand(): AddPurchaseOrderLine
    {
        return new AddPurchaseOrderLine(
            purchaseOrderId: self::PO_UUID,
            clinicId: self::CLINIC_UUID,
            articleId: self::ARTICLE_UUID,
            catalogEntryId: self::CATALOG_ENTRY_ID,
            orderedAmount: '10',
            orderedUnit: 'UNIT',
            unitPriceMinor: 1000,
            unitPriceCurrency: 'EUR',
            note: null,
        );
    }

    private function makeDraftPurchaseOrder(): PurchaseOrder
    {
        return PurchaseOrder::create(
            id: PurchaseOrderId::fromString(self::PO_UUID),
            clinicId: ClinicId::fromString(self::CLINIC_UUID),
            supplierId: SupplierId::fromString(self::SUPPLIER_UUID),
            supplierAccountId: SupplierAccountId::fromString(self::ACCOUNT_UUID),
            orderNumber: PurchaseOrderNumber::fromString('PO-2026-000001'),
            currency: CurrencyCode::fromString('EUR'),
            deliveryAddress: Address::create(null, null, null, null, null),
        );
    }
}
