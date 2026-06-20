<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Command;

use App\Context\Procurement\Application\Command\UpdatePurchaseOrderLine\UpdatePurchaseOrderLine;
use App\Context\Procurement\Application\Command\UpdatePurchaseOrderLine\UpdatePurchaseOrderLineHandler;
use App\Context\Procurement\Domain\PurchaseOrder\Exception\PurchaseOrderNotFoundException;
use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Context\Procurement\Domain\PurchaseOrder\Repository\PurchaseOrderRepositoryInterface;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderNumber;
use App\Context\Procurement\Domain\Shared\ValueObject\Address;
use App\Context\Procurement\Domain\Shared\ValueObject\ArticleId;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class UpdatePurchaseOrderLineHandlerTest extends TestCase
{
    private const string PO_UUID          = '01932b00-0000-7000-8000-000000000100';
    private const string LINE_UUID        = '01932b00-0000-7000-8000-000000000101';
    private const string CLINIC_UUID      = '01932b00-0000-7000-8000-000000000003';
    private const string SUPPLIER_UUID    = '01932b00-0000-7000-8000-000000000001';
    private const string ACCOUNT_UUID     = '01932b00-0000-7000-8000-000000000002';
    private const string ARTICLE_UUID     = '01932b00-0000-7000-8000-000000000200';
    private const string CATALOG_ENTRY_ID = '01932b00-0000-7000-8000-000000000300';

    public function testItUpdatesExistingLine(): void
    {
        $po = $this->makeDraftPurchaseOrderWithLine();

        $repository = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $repository->method('findById')->willReturn($po);
        $repository->expects(self::once())->method('save');

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        ($this->makeHandler($repository, $eventBus))(new UpdatePurchaseOrderLine(
            purchaseOrderId: self::PO_UUID,
            lineId: self::LINE_UUID,
            orderedAmount: '5',
            unitPriceMinor: 2000,
            unitPriceCurrency: 'EUR',
            note: 'Updated note',
        ));
    }

    public function testItThrowsWhenPurchaseOrderNotFound(): void
    {
        $repository = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $eventBus = $this->createStub(EventBusInterface::class);

        $this->expectException(PurchaseOrderNotFoundException::class);

        ($this->makeHandler($repository, $eventBus))(new UpdatePurchaseOrderLine(
            purchaseOrderId: self::PO_UUID,
            lineId: self::LINE_UUID,
            orderedAmount: '5',
            unitPriceMinor: 2000,
            unitPriceCurrency: 'EUR',
            note: null,
        ));
    }

    public function testItThrowsWhenOrderedAmountIsNotNumeric(): void
    {
        $po = $this->makeDraftPurchaseOrderWithLine();

        $repository = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $repository->method('findById')->willReturn($po);

        $eventBus = $this->createStub(EventBusInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('orderedAmount "xyz" is not numeric.');

        ($this->makeHandler($repository, $eventBus))(new UpdatePurchaseOrderLine(
            purchaseOrderId: self::PO_UUID,
            lineId: self::LINE_UUID,
            orderedAmount: 'xyz',
            unitPriceMinor: 2000,
            unitPriceCurrency: 'EUR',
            note: null,
        ));
    }

    private function makeHandler(
        PurchaseOrderRepositoryInterface $repository,
        EventBusInterface $eventBus,
    ): UpdatePurchaseOrderLineHandler {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-15'));

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(static function (callable $fn): void {
            $fn();
        });

        return new UpdatePurchaseOrderLineHandler(
            $repository,
            new DomainEventPublisher($eventBus),
            $clock,
            $em,
        );
    }

    private function makeDraftPurchaseOrderWithLine(): PurchaseOrder
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
            catalogEntryId: SupplierCatalogEntryId::fromString(self::CATALOG_ENTRY_ID),
            orderedAmount: '10',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: Money::fromMinorUnits(1000, CurrencyCode::fromString('EUR')),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );
        $_ = $po->pullDomainEvents();

        return $po;
    }
}
