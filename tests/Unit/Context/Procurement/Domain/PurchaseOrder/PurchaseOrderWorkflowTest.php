<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Domain\PurchaseOrder;

use App\Context\Procurement\Application\Exception\CancelledLineCannotReceiveException;
use App\Context\Procurement\Application\Exception\ReceiptQuantityExceedsOrderedException;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderCancelled;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderClosed;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderConfirmed;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderFullyReceived;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderLineCancelled;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderPartiallyReceived;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderSendFailed;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderSubmitted;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderSubmittingStarted;
use App\Context\Procurement\Domain\PurchaseOrder\Exception\InvalidPurchaseOrderStatusTransitionException;
use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
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
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class PurchaseOrderWorkflowTest extends TestCase
{
    private const string PO_ID            = '01932b00-0000-7000-8000-000000000100';
    private const string CLINIC_ID        = '01932b00-0000-7000-8000-000000000003';
    private const string SUPPLIER_ID      = '01932b00-0000-7000-8000-000000000001';
    private const string ACCOUNT_ID       = '01932b00-0000-7000-8000-000000000002';
    private const string LINE_ID_1        = '01932b00-0000-7000-8000-000000000101';
    private const string LINE_ID_2        = '01932b00-0000-7000-8000-000000000102';
    private const string ARTICLE_ID       = '01932b00-0000-7000-8000-000000000200';
    private const string CATALOG_ENTRY_ID = '01932b00-0000-7000-8000-000000000300';

    // --- Submit workflow ---

    public function testMarkAsSubmittingTransitionsToSubmitting(): void
    {
        $po = $this->poWithOneLine();

        $po->markAsSubmitting(new \DateTimeImmutable());
        $events = $po->pullDomainEvents();

        self::assertSame(PurchaseOrderStatus::SUBMITTING, $po->status());
        self::assertCount(1, $events);
        self::assertInstanceOf(PurchaseOrderSubmittingStarted::class, $events[0]);
    }

    public function testSubmitTransitionsToSubmitted(): void
    {
        $po = $this->poWithOneLine();
        $po->markAsSubmitting(new \DateTimeImmutable());
        $_ = $po->pullDomainEvents();

        $ref = ExternalReference::create('EXT-001', 'centravet');
        $po->submit($ref, new \DateTimeImmutable('2026-01-02'), new \DateTimeImmutable('2026-01-02'));
        $events = $po->pullDomainEvents();

        self::assertSame(PurchaseOrderStatus::SUBMITTED, $po->status());
        self::assertCount(1, $events);
        self::assertInstanceOf(PurchaseOrderSubmitted::class, $events[0]);
        self::assertNotNull($po->submittedAt());
        self::assertNotNull($po->externalReference());
        self::assertSame('EXT-001', $po->externalReference()->value);
    }

    public function testSubmitOnNonSubmittingThrows(): void
    {
        $po = $this->poWithOneLine();

        $this->expectException(InvalidPurchaseOrderStatusTransitionException::class);
        $po->submit(
            ExternalReference::create('EXT-001', 'centravet'),
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
        );
    }

    // --- Send failed / retry ---

    public function testMarkAsSendingFailedTransitionsToSendFailed(): void
    {
        $po = $this->poWithOneLine();
        $po->markAsSubmitting(new \DateTimeImmutable());
        $_ = $po->pullDomainEvents();

        $po->markAsSendingFailed('Timeout', new \DateTimeImmutable());
        $events = $po->pullDomainEvents();

        self::assertSame(PurchaseOrderStatus::SEND_FAILED, $po->status());
        self::assertCount(1, $events);
        self::assertInstanceOf(PurchaseOrderSendFailed::class, $events[0]);
    }

    public function testRetryResetFromSendFailedTransitionsToDraft(): void
    {
        $po = $this->poWithOneLine();
        $po->markAsSubmitting(new \DateTimeImmutable());
        $po->markAsSendingFailed('Timeout', new \DateTimeImmutable());
        $_ = $po->pullDomainEvents();

        $po->retryReset(new \DateTimeImmutable());

        self::assertSame(PurchaseOrderStatus::DRAFT, $po->status());
    }

    public function testRetryResetOnNonSendFailedThrows(): void
    {
        $po = $this->poWithOneLine();

        $this->expectException(InvalidPurchaseOrderStatusTransitionException::class);
        $po->retryReset(new \DateTimeImmutable());
    }

    public function testMarkAsSubmittingFromSendFailedSucceeds(): void
    {
        $po = $this->poWithOneLine();
        $po->markAsSubmitting(new \DateTimeImmutable());
        $po->markAsSendingFailed('Timeout', new \DateTimeImmutable());
        $_ = $po->pullDomainEvents();

        // Can retry directly from SEND_FAILED
        $po->markAsSubmitting(new \DateTimeImmutable());
        $events = $po->pullDomainEvents();

        self::assertSame(PurchaseOrderStatus::SUBMITTING, $po->status());
        self::assertInstanceOf(PurchaseOrderSubmittingStarted::class, $events[0]);
    }

    // --- Confirm ---

    public function testConfirmTransitionsToConfirmed(): void
    {
        $po = $this->poSubmitted();

        $po->confirm(new \DateTimeImmutable('2026-01-03'), new \DateTimeImmutable('2026-01-03'));
        $events = $po->pullDomainEvents();

        self::assertSame(PurchaseOrderStatus::CONFIRMED, $po->status());
        self::assertCount(1, $events);
        self::assertInstanceOf(PurchaseOrderConfirmed::class, $events[0]);
        self::assertNotNull($po->confirmedAt());
    }

    public function testConfirmOnNonSubmittedThrows(): void
    {
        $po = $this->poWithOneLine();

        $this->expectException(InvalidPurchaseOrderStatusTransitionException::class);
        $po->confirm(new \DateTimeImmutable(), new \DateTimeImmutable());
    }

    // --- Partial reception ---

    public function testRecordPartialReceptionTransitionsToPartiallyReceived(): void
    {
        $po = $this->poConfirmed();

        $po->recordPartialReception(
            [self::LINE_ID_1 => '3'],
            new \DateTimeImmutable(),
        );
        $events = $po->pullDomainEvents();

        self::assertSame(PurchaseOrderStatus::PARTIALLY_RECEIVED, $po->status());
        self::assertCount(1, $events);
        self::assertInstanceOf(PurchaseOrderPartiallyReceived::class, $events[0]);

        // Line should still be ACTIVE (received 3 out of 10)
        $lines = $po->lines();
        self::assertSame(PurchaseOrderLineStatus::ACTIVE, $lines[0]->status());
        self::assertSame('3.000000', $lines[0]->receivedAmount());
    }

    public function testRecordPartialReceptionOnWrongStatusThrows(): void
    {
        $po = $this->poWithOneLine();

        $this->expectException(InvalidPurchaseOrderStatusTransitionException::class);
        $po->recordPartialReception([self::LINE_ID_1 => '3'], new \DateTimeImmutable());
    }

    // --- Full reception ---

    public function testRecordFullReceptionTransitionsToReceived(): void
    {
        $po = $this->poConfirmed();

        $po->recordFullReception(
            [self::LINE_ID_1 => '10'],
            new \DateTimeImmutable(),
        );
        $events = $po->pullDomainEvents();

        self::assertSame(PurchaseOrderStatus::RECEIVED, $po->status());
        self::assertCount(1, $events);
        self::assertInstanceOf(PurchaseOrderFullyReceived::class, $events[0]);

        // Line should be FULLY_RECEIVED
        $lines = $po->lines();
        self::assertSame(PurchaseOrderLineStatus::FULLY_RECEIVED, $lines[0]->status());
    }

    public function testReceiptQuantityExceedsOrderedThrows(): void
    {
        $po = $this->poConfirmed();

        $this->expectException(ReceiptQuantityExceedsOrderedException::class);
        $po->recordFullReception([self::LINE_ID_1 => '999'], new \DateTimeImmutable());
    }

    // --- Cancel line ---

    public function testCancelLineOnConfirmedPO(): void
    {
        $po = $this->poConfirmed();

        $po->cancelLine(
            PurchaseOrderLineId::fromString(self::LINE_ID_1),
            'Out of stock',
            new \DateTimeImmutable(),
        );
        $events = $po->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(PurchaseOrderLineCancelled::class, $events[0]);

        $lines = $po->lines();
        self::assertSame(PurchaseOrderLineStatus::CANCELLED, $lines[0]->status());
    }

    public function testCancelLineOnDraftThrows(): void
    {
        $po = $this->poWithOneLine();

        $this->expectException(InvalidPurchaseOrderStatusTransitionException::class);
        $po->cancelLine(
            PurchaseOrderLineId::fromString(self::LINE_ID_1),
            'reason',
            new \DateTimeImmutable(),
        );
    }

    public function testReceiveOnCancelledLineThrows(): void
    {
        $po = $this->poConfirmed();
        $po->cancelLine(
            PurchaseOrderLineId::fromString(self::LINE_ID_1),
            'Out of stock',
            new \DateTimeImmutable(),
        );
        $_ = $po->pullDomainEvents();

        $this->expectException(CancelledLineCannotReceiveException::class);
        $po->recordPartialReception([self::LINE_ID_1 => '1'], new \DateTimeImmutable());
    }

    // --- Close ---

    public function testCloseFromReceived(): void
    {
        $po = $this->poConfirmed();
        $po->recordFullReception([self::LINE_ID_1 => '10'], new \DateTimeImmutable());
        $_ = $po->pullDomainEvents();

        $po->close(new \DateTimeImmutable());
        $events = $po->pullDomainEvents();

        self::assertSame(PurchaseOrderStatus::CLOSED, $po->status());
        self::assertCount(1, $events);
        self::assertInstanceOf(PurchaseOrderClosed::class, $events[0]);
    }

    public function testCloseFromPartiallyReceivedAutoCancelsRemainingLines(): void
    {
        $po = $this->poConfirmed();
        $po->recordPartialReception([self::LINE_ID_1 => '3'], new \DateTimeImmutable());
        $_ = $po->pullDomainEvents();

        $po->close(new \DateTimeImmutable());

        self::assertSame(PurchaseOrderStatus::CLOSED, $po->status());

        // Remaining ACTIVE line should be auto-cancelled
        $lines = $po->lines();
        self::assertSame(PurchaseOrderLineStatus::CANCELLED, $lines[0]->status());
    }

    public function testCloseOnNonReceivableStatusThrows(): void
    {
        $po = $this->poSubmitted();

        $this->expectException(InvalidPurchaseOrderStatusTransitionException::class);
        $po->close(new \DateTimeImmutable());
    }

    // --- Cancel PO ---

    public function testCancelDraftPO(): void
    {
        $po = $this->poWithOneLine();

        $po->cancel('Changed mind', new \DateTimeImmutable());
        $events = $po->pullDomainEvents();

        self::assertSame(PurchaseOrderStatus::CANCELLED, $po->status());
        self::assertCount(1, $events);
        self::assertInstanceOf(PurchaseOrderCancelled::class, $events[0]);
    }

    public function testCancelSubmittedPO(): void
    {
        $po = $this->poSubmitted();

        $po->cancel('Supplier unavailable', new \DateTimeImmutable());
        $events = $po->pullDomainEvents();

        self::assertSame(PurchaseOrderStatus::CANCELLED, $po->status());
        self::assertInstanceOf(PurchaseOrderCancelled::class, $events[0]);
    }

    public function testCancelAlreadyClosedThrows(): void
    {
        $po = $this->poConfirmed();
        $po->recordFullReception([self::LINE_ID_1 => '10'], new \DateTimeImmutable());
        $po->close(new \DateTimeImmutable());
        $_ = $po->pullDomainEvents();

        $this->expectException(InvalidPurchaseOrderStatusTransitionException::class);
        $po->cancel('reason', new \DateTimeImmutable());
    }

    // --- linesActiveRemaining ---

    public function testLinesActiveRemainingFiltersCorrectly(): void
    {
        $po = $this->makePurchaseOrder();
        $_  = $po->pullDomainEvents();

        $lineId1 = PurchaseOrderLineId::fromString(self::LINE_ID_1);
        $lineId2 = PurchaseOrderLineId::fromString(self::LINE_ID_2);

        $po->addLine(
            lineId: $lineId1,
            articleId: ArticleId::fromString(self::ARTICLE_ID),
            catalogEntryId: SupplierCatalogEntryId::fromString(self::CATALOG_ENTRY_ID),
            orderedAmount: '5',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: $this->makeMoney(),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );
        $po->addLine(
            lineId: $lineId2,
            articleId: ArticleId::fromString(self::ARTICLE_ID),
            catalogEntryId: SupplierCatalogEntryId::fromString(self::CATALOG_ENTRY_ID),
            orderedAmount: '5',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: $this->makeMoney(),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );
        $po->removeLine($lineId2, new \DateTimeImmutable());
        $_ = $po->pullDomainEvents();

        // Only lineId1 remains
        $active = $po->linesActiveRemaining();
        self::assertCount(1, $active);
        self::assertTrue($active[0]->id()->equals($lineId1));
    }

    private function makePurchaseOrder(): PurchaseOrder
    {
        return PurchaseOrder::create(
            id: PurchaseOrderId::fromString(self::PO_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            supplierId: SupplierId::fromString(self::SUPPLIER_ID),
            supplierAccountId: SupplierAccountId::fromString(self::ACCOUNT_ID),
            orderNumber: PurchaseOrderNumber::fromString('PO-2026-000001'),
            currency: CurrencyCode::fromString('EUR'),
            deliveryAddress: Address::create(null, null, null, null, null),
            createdAt: new \DateTimeImmutable('2026-01-01'),
        );
    }

    private function makeMoney(int $minorUnits = 1000): Money
    {
        return Money::fromMinorUnits($minorUnits, CurrencyCode::fromString('EUR'));
    }

    /** Builds a PO in DRAFT with one active line. */
    private function poWithOneLine(): PurchaseOrder
    {
        $po = $this->makePurchaseOrder();
        $_  = $po->pullDomainEvents();

        $po->addLine(
            lineId: PurchaseOrderLineId::fromString(self::LINE_ID_1),
            articleId: ArticleId::fromString(self::ARTICLE_ID),
            catalogEntryId: SupplierCatalogEntryId::fromString(self::CATALOG_ENTRY_ID),
            orderedAmount: '10',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: $this->makeMoney(500),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );
        $_ = $po->pullDomainEvents();

        return $po;
    }

    /** Advances a DRAFT PO to SUBMITTED status. */
    private function poSubmitted(): PurchaseOrder
    {
        $po = $this->poWithOneLine();

        $po->markAsSubmitting(new \DateTimeImmutable());
        $po->submit(
            ExternalReference::create('EXT-001', 'centravet'),
            new \DateTimeImmutable('2026-01-02'),
            new \DateTimeImmutable('2026-01-02'),
        );
        $_ = $po->pullDomainEvents();

        return $po;
    }

    /** Advances a DRAFT PO to CONFIRMED status. */
    private function poConfirmed(): PurchaseOrder
    {
        $po = $this->poSubmitted();

        $po->confirm(new \DateTimeImmutable('2026-01-03'), new \DateTimeImmutable('2026-01-03'));
        $_ = $po->pullDomainEvents();

        return $po;
    }
}
