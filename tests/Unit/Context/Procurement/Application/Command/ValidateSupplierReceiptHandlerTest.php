<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Command;

use App\Context\Procurement\Application\Command\ValidateSupplierReceipt\ValidateSupplierReceipt;
use App\Context\Procurement\Application\Command\ValidateSupplierReceipt\ValidateSupplierReceiptHandler;
use App\Context\Procurement\Application\Exception\PurchaseOrderClosedOrCancelledException;
use App\Context\Procurement\Domain\PurchaseOrder\Entity\PurchaseOrderLine;
use App\Context\Procurement\Domain\PurchaseOrder\Exception\PurchaseOrderNotFoundException;
use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Context\Procurement\Domain\PurchaseOrder\Repository\PurchaseOrderRepositoryInterface;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\ExternalReference;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineStatus;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderNumber;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderStatus;
use App\Context\Procurement\Domain\Shared\ValueObject\Address;
use App\Context\Procurement\Domain\Shared\ValueObject\ArticleId;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierReceipt\Entity\SupplierReceiptLine;
use App\Context\Procurement\Domain\SupplierReceipt\Exception\SupplierReceiptNotFoundException;
use App\Context\Procurement\Domain\SupplierReceipt\Repository\SupplierReceiptRepositoryInterface;
use App\Context\Procurement\Domain\SupplierReceipt\SupplierReceipt;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\DeliveryNoteReference;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\ReceiptMatchType;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptId;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptLineId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Bus\IntegrationEventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Application\Event\IntegrationEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ValidateSupplierReceiptHandlerTest extends TestCase
{
    private const string RECEIPT_UUID  = '01932b00-0000-7000-8000-000000000400';
    private const string PO_UUID       = '01932b00-0000-7000-8000-000000000100';
    private const string LINE_UUID     = '01932b00-0000-7000-8000-000000000101';
    private const string RECEIPT_LINE  = '01932b00-0000-7000-8000-000000000401';
    private const string CLINIC_UUID   = '01932b00-0000-7000-8000-000000000003';
    private const string SUPPLIER_UUID = '01932b00-0000-7000-8000-000000000001';
    private const string ACCOUNT_UUID  = '01932b00-0000-7000-8000-000000000002';
    private const string ARTICLE_UUID  = '01932b00-0000-7000-8000-000000000200';
    private const string CATALOG_UUID  = '01932b00-0000-7000-8000-000000000300';

    public function testItPublishesEventBusTwiceAndIntegrationEventBusOnce(): void
    {
        $receipt = $this->makePendingReceipt();
        $po      = $this->makeConfirmedPurchaseOrder();

        $receiptRepo = $this->createStub(SupplierReceiptRepositoryInterface::class);
        $receiptRepo->method('findById')->willReturn($receipt);

        $poRepo = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $poRepo->method('findById')->willReturn($po);

        // eventBus.publish called twice: once for receipt events, once for PO events
        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::exactly(2))->method('publish');

        // integration event bus called once
        $integrationEventBus = $this->createMock(IntegrationEventBusInterface::class);
        $integrationEventBus->expects(self::once())->method('publish');

        ($this->makeHandler($receiptRepo, $poRepo, $eventBus, $integrationEventBus))(
            new ValidateSupplierReceipt(receiptId: self::RECEIPT_UUID, clinicId: self::CLINIC_UUID)
        );
    }

    public function testItThrowsWhenPoIsNotConfirmedOrPartiallyReceived(): void
    {
        $receipt = $this->makePendingReceipt();

        // Build a cancelled PO
        $po = $this->makeDraftPurchaseOrder();
        $po->cancel('Cancelled', new \DateTimeImmutable());
        $_ = $po->pullDomainEvents();

        $receiptRepo = $this->createStub(SupplierReceiptRepositoryInterface::class);
        $receiptRepo->method('findById')->willReturn($receipt);

        $poRepo = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $poRepo->method('findById')->willReturn($po);

        $eventBus            = $this->createStub(EventBusInterface::class);
        $integrationEventBus = $this->createStub(IntegrationEventBusInterface::class);

        $this->expectException(PurchaseOrderClosedOrCancelledException::class);

        ($this->makeHandler($receiptRepo, $poRepo, $eventBus, $integrationEventBus))(
            new ValidateSupplierReceipt(receiptId: self::RECEIPT_UUID, clinicId: self::CLINIC_UUID)
        );
    }

    public function testItRecordsFullReceptionWhenAllLinesAreSatisfied(): void
    {
        // Receipt delivers 5 out of 5 ordered — full reception
        $receipt = $this->makePendingReceipt(receivedAmount: '5');
        $po      = $this->makeConfirmedPurchaseOrder();

        $receiptRepo = $this->createStub(SupplierReceiptRepositoryInterface::class);
        $receiptRepo->method('findById')->willReturn($receipt);

        $poRepo = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $poRepo->method('findById')->willReturn($po);

        $eventBus            = $this->createStub(EventBusInterface::class);
        $integrationEventBus = $this->createStub(IntegrationEventBusInterface::class);

        ($this->makeHandler($receiptRepo, $poRepo, $eventBus, $integrationEventBus))(
            new ValidateSupplierReceipt(receiptId: self::RECEIPT_UUID, clinicId: self::CLINIC_UUID)
        );

        self::assertSame(PurchaseOrderStatus::RECEIVED, $po->status());
    }

    public function testItRecordsPartialReceptionWhenSomeLinesRemain(): void
    {
        // Receipt delivers only 2 out of 5 ordered units — partial reception
        $receipt = $this->makePendingReceipt(receivedAmount: '2');
        $po      = $this->makeConfirmedPurchaseOrder();

        $receiptRepo = $this->createStub(SupplierReceiptRepositoryInterface::class);
        $receiptRepo->method('findById')->willReturn($receipt);

        $poRepo = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $poRepo->method('findById')->willReturn($po);

        $eventBus            = $this->createStub(EventBusInterface::class);
        $integrationEventBus = $this->createStub(IntegrationEventBusInterface::class);

        ($this->makeHandler($receiptRepo, $poRepo, $eventBus, $integrationEventBus))(
            new ValidateSupplierReceipt(receiptId: self::RECEIPT_UUID, clinicId: self::CLINIC_UUID)
        );

        self::assertSame(PurchaseOrderStatus::PARTIALLY_RECEIVED, $po->status());
    }

    public function testItThrowsWhenReceiptNotFound(): void
    {
        $receiptRepo = $this->createStub(SupplierReceiptRepositoryInterface::class);
        $receiptRepo->method('findById')->willReturn(null);

        $poRepo              = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $eventBus            = $this->createStub(EventBusInterface::class);
        $integrationEventBus = $this->createStub(IntegrationEventBusInterface::class);

        $this->expectException(SupplierReceiptNotFoundException::class);

        ($this->makeHandler($receiptRepo, $poRepo, $eventBus, $integrationEventBus))(
            new ValidateSupplierReceipt(receiptId: self::RECEIPT_UUID, clinicId: self::CLINIC_UUID)
        );
    }

    public function testItThrowsWhenPurchaseOrderNotFound(): void
    {
        $receipt = $this->makePendingReceipt();

        $receiptRepo = $this->createStub(SupplierReceiptRepositoryInterface::class);
        $receiptRepo->method('findById')->willReturn($receipt);

        $poRepo = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $poRepo->method('findById')->willReturn(null);

        $eventBus            = $this->createStub(EventBusInterface::class);
        $integrationEventBus = $this->createStub(IntegrationEventBusInterface::class);

        $this->expectException(PurchaseOrderNotFoundException::class);

        ($this->makeHandler($receiptRepo, $poRepo, $eventBus, $integrationEventBus))(
            new ValidateSupplierReceipt(receiptId: self::RECEIPT_UUID, clinicId: self::CLINIC_UUID)
        );
    }

    public function testItThrowsWhenReceiptLineHasNonNumericReceivedAmount(): void
    {
        // Non-numeric receivedAmount on the receipt line — defensive guard
        $receipt = $this->makePendingReceipt(receivedAmount: 'not-a-number');
        $po      = $this->makeConfirmedPurchaseOrder();

        $receiptRepo = $this->createStub(SupplierReceiptRepositoryInterface::class);
        $receiptRepo->method('findById')->willReturn($receipt);

        $poRepo = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $poRepo->method('findById')->willReturn($po);

        $eventBus            = $this->createStub(EventBusInterface::class);
        $integrationEventBus = $this->createStub(IntegrationEventBusInterface::class);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/non-numeric receivedAmount/');

        ($this->makeHandler($receiptRepo, $poRepo, $eventBus, $integrationEventBus))(
            new ValidateSupplierReceipt(receiptId: self::RECEIPT_UUID, clinicId: self::CLINIC_UUID)
        );
    }

    public function testItThrowsWhenPurchaseOrderLineHasNonNumericOrderedAmount(): void
    {
        $receipt = $this->makePendingReceipt(receivedAmount: '5');

        // Build a PO line with a non-numeric orderedAmount, bypassing the numeric-string contract via reflection.
        $poDraft    = $this->makeDraftPurchaseOrder();
        $brokenLine = PurchaseOrderLine::reconstitute(
            id: PurchaseOrderLineId::fromString(self::LINE_UUID),
            articleId: ArticleId::fromString(self::ARTICLE_UUID),
            catalogEntryId: SupplierCatalogEntryId::fromString(self::CATALOG_UUID),
            orderedAmount: '1',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: Money::fromMinorUnits(1000, CurrencyCode::fromString('EUR')),
            receivedAmount: '0',
            status: PurchaseOrderLineStatus::ACTIVE,
            note: null,
        );
        $reflection = new \ReflectionClass($brokenLine);
        $property   = $reflection->getProperty('orderedAmount');
        $property->setValue($brokenLine, 'not-a-number');
        $po = PurchaseOrder::reconstitute(
            id: PurchaseOrderId::fromString(self::PO_UUID),
            clinicId: ClinicId::fromString(self::CLINIC_UUID),
            supplierId: SupplierId::fromString(self::SUPPLIER_UUID),
            supplierAccountId: SupplierAccountId::fromString(self::ACCOUNT_UUID),
            orderNumber: PurchaseOrderNumber::fromString('PO-2026-000888'),
            currency: CurrencyCode::fromString('EUR'),
            status: PurchaseOrderStatus::CONFIRMED,
            externalReference: null,
            deliveryAddress: Address::create(null, null, null, null, null),
            pdfFileId: null,
            submittedAt: null,
            confirmedAt: null,
            lines: [$brokenLine],
            version: 1,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        $receiptRepo = $this->createStub(SupplierReceiptRepositoryInterface::class);
        $receiptRepo->method('findById')->willReturn($receipt);

        $poRepo = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $poRepo->method('findById')->willReturn($po);

        $eventBus            = $this->createStub(EventBusInterface::class);
        $integrationEventBus = $this->createStub(IntegrationEventBusInterface::class);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('PO line has non-numeric amount.');

        ($this->makeHandler($receiptRepo, $poRepo, $eventBus, $integrationEventBus))(
            new ValidateSupplierReceipt(receiptId: self::RECEIPT_UUID, clinicId: self::CLINIC_UUID)
        );

        $_ = $poDraft;
    }

    public function testItSkipsCancelledLinesWhenComputingFullReception(): void
    {
        $receipt = $this->makePendingReceipt(receivedAmount: '5');
        $po      = $this->makeConfirmedPurchaseOrderWithCancelledLine();

        $receiptRepo = $this->createStub(SupplierReceiptRepositoryInterface::class);
        $receiptRepo->method('findById')->willReturn($receipt);

        $poRepo = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $poRepo->method('findById')->willReturn($po);

        $eventBus            = $this->createStub(EventBusInterface::class);
        $integrationEventBus = $this->createStub(IntegrationEventBusInterface::class);

        ($this->makeHandler($receiptRepo, $poRepo, $eventBus, $integrationEventBus))(
            new ValidateSupplierReceipt(receiptId: self::RECEIPT_UUID, clinicId: self::CLINIC_UUID)
        );

        // PO has 2 lines (1 active fully received, 1 cancelled). Cancelled is skipped → full reception
        self::assertSame(PurchaseOrderStatus::RECEIVED, $po->status());
    }

    private function makeHandler(
        SupplierReceiptRepositoryInterface $receiptRepo,
        PurchaseOrderRepositoryInterface $poRepo,
        EventBusInterface $eventBus,
        IntegrationEventBusInterface $integrationEventBus,
    ): ValidateSupplierReceiptHandler {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-15'));

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(static function (callable $fn): void {
            $fn();
        });

        return new ValidateSupplierReceiptHandler(
            $receiptRepo,
            $poRepo,
            new DomainEventPublisher($eventBus),
            new IntegrationEventPublisher($integrationEventBus),
            $clock,
            $em,
        );
    }

    private function makePendingReceipt(string $receivedAmount = '5'): SupplierReceipt
    {
        $receipt = SupplierReceipt::create(
            id: SupplierReceiptId::fromString(self::RECEIPT_UUID),
            clinicId: ClinicId::fromString(self::CLINIC_UUID),
            supplierId: SupplierId::fromString(self::SUPPLIER_UUID),
            purchaseOrderId: PurchaseOrderId::fromString(self::PO_UUID),
            deliveryNoteReference: DeliveryNoteReference::fromString('DN-001'),
            matchType: ReceiptMatchType::AUTO_MATCHED,
        );
        $_ = $receipt->pullDomainEvents();

        $line = SupplierReceiptLine::create(
            id: SupplierReceiptLineId::fromString(self::RECEIPT_LINE),
            purchaseOrderLineId: PurchaseOrderLineId::fromString(self::LINE_UUID),
            articleId: ArticleId::fromString(self::ARTICLE_UUID),
            receivedAmount: $receivedAmount,
            receivedUnit: UnitOfMeasure::fromString('UNIT'),
        );
        $receipt->addLine($line, new \DateTimeImmutable());
        $_ = $receipt->pullDomainEvents();

        return $receipt;
    }

    private function makeConfirmedPurchaseOrder(): PurchaseOrder
    {
        $po = $this->makeDraftPurchaseOrder();

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
        $po->markAsSubmitting(new \DateTimeImmutable());
        $po->submit(ExternalReference::create('EXT-001', 'test'), new \DateTimeImmutable(), new \DateTimeImmutable());
        $po->confirm(new \DateTimeImmutable(), new \DateTimeImmutable());
        $_ = $po->pullDomainEvents();

        return $po;
    }

    private function makeConfirmedPurchaseOrderWithCancelledLine(): PurchaseOrder
    {
        $po = $this->makeDraftPurchaseOrder();

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
        $extraLineId = '01932b00-0000-7000-8000-000000000102';
        $po->addLine(
            lineId: PurchaseOrderLineId::fromString($extraLineId),
            articleId: ArticleId::fromString(self::ARTICLE_UUID),
            catalogEntryId: SupplierCatalogEntryId::fromString(self::CATALOG_UUID),
            orderedAmount: '3',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: Money::fromMinorUnits(500, CurrencyCode::fromString('EUR')),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );
        $po->markAsSubmitting(new \DateTimeImmutable());
        $po->submit(ExternalReference::create('EXT-001', 'test'), new \DateTimeImmutable(), new \DateTimeImmutable());
        $po->confirm(new \DateTimeImmutable(), new \DateTimeImmutable());
        $po->cancelLine(PurchaseOrderLineId::fromString($extraLineId), 'no longer needed', new \DateTimeImmutable());
        $_ = $po->pullDomainEvents();

        return $po;
    }

    private function makeDraftPurchaseOrder(): PurchaseOrder
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

        return $po;
    }
}
