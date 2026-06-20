<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Domain\SupplierReceipt;

use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineId;
use App\Context\Procurement\Domain\Shared\ValueObject\ArticleId;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierReceipt\Entity\SupplierReceiptLine;
use App\Context\Procurement\Domain\SupplierReceipt\Event\SupplierReceiptCompleted;
use App\Context\Procurement\Domain\SupplierReceipt\Event\SupplierReceiptCreated;
use App\Context\Procurement\Domain\SupplierReceipt\Event\SupplierReceiptLineAdded;
use App\Context\Procurement\Domain\SupplierReceipt\Event\SupplierReceiptLineRemoved;
use App\Context\Procurement\Domain\SupplierReceipt\Event\SupplierReceiptValidated;
use App\Context\Procurement\Domain\SupplierReceipt\Exception\ReceiptValidationException;
use App\Context\Procurement\Domain\SupplierReceipt\SupplierReceipt;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\DeliveryNoteReference;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\ReceiptMatchType;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptId;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptLineId;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptStatus;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use PHPUnit\Framework\TestCase;

final class SupplierReceiptTest extends TestCase
{
    private const string RECEIPT_ID   = '01932b00-0000-7000-8000-000000000500';
    private const string CLINIC_ID    = '01932b00-0000-7000-8000-000000000003';
    private const string SUPPLIER_ID  = '01932b00-0000-7000-8000-000000000001';
    private const string PO_ID        = '01932b00-0000-7000-8000-000000000100';
    private const string LINE_ID_1    = '01932b00-0000-7000-8000-000000000501';
    private const string PO_LINE_ID_1 = '01932b00-0000-7000-8000-000000000101';
    private const string ARTICLE_ID   = '01932b00-0000-7000-8000-000000000200';

    public function testCreateRaisesSupplierReceiptCreated(): void
    {
        $receipt = $this->makeReceipt();
        $events  = $receipt->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(SupplierReceiptCreated::class, $events[0]);
        self::assertSame(self::RECEIPT_ID, $events[0]->aggregateId());
        self::assertSame(SupplierReceiptStatus::PENDING_REVIEW, $receipt->status());
    }

    public function testAddLineRaisesSupplierReceiptLineAdded(): void
    {
        $receipt = $this->makeReceipt();
        $_       = $receipt->pullDomainEvents();

        $line = $this->makeLine();
        $receipt->addLine($line, new \DateTimeImmutable());
        $events = $receipt->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(SupplierReceiptLineAdded::class, $events[0]);
        self::assertCount(1, $receipt->lines());
    }

    public function testRemoveLineRaisesSupplierReceiptLineRemoved(): void
    {
        $receipt = $this->makeReceipt();
        $_       = $receipt->pullDomainEvents();

        $line = $this->makeLine();
        $receipt->addLine($line, new \DateTimeImmutable());
        $_ = $receipt->pullDomainEvents();

        $receipt->removeLine(SupplierReceiptLineId::fromString(self::LINE_ID_1), new \DateTimeImmutable());
        $events = $receipt->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(SupplierReceiptLineRemoved::class, $events[0]);
        self::assertCount(0, $receipt->lines());
    }

    public function testValidateTransitionsToValidatedAndRaisesBothEvents(): void
    {
        $receipt = $this->makeReceipt();
        $_       = $receipt->pullDomainEvents();

        $validatedAt = new \DateTimeImmutable('2026-01-10T12:00:00+00:00');
        $receipt->validate($validatedAt, new \DateTimeImmutable());
        $events = $receipt->pullDomainEvents();

        self::assertSame(SupplierReceiptStatus::VALIDATED, $receipt->status());
        self::assertCount(2, $events);
        self::assertInstanceOf(SupplierReceiptValidated::class, $events[0]);
        self::assertInstanceOf(SupplierReceiptCompleted::class, $events[1]);
        self::assertNotNull($receipt->validatedAt());
    }

    public function testValidateAlreadyValidatedThrows(): void
    {
        $receipt = $this->makeReceipt();
        $_       = $receipt->pullDomainEvents();

        $receipt->validate(new \DateTimeImmutable(), new \DateTimeImmutable());
        $_ = $receipt->pullDomainEvents();

        $this->expectException(ReceiptValidationException::class);
        $receipt->validate(new \DateTimeImmutable(), new \DateTimeImmutable());
    }

    public function testAddLineOnValidatedThrows(): void
    {
        $receipt = $this->makeReceipt();
        $_       = $receipt->pullDomainEvents();

        $receipt->validate(new \DateTimeImmutable(), new \DateTimeImmutable());
        $_ = $receipt->pullDomainEvents();

        $this->expectException(ReceiptValidationException::class);
        $receipt->addLine($this->makeLine(), new \DateTimeImmutable());
    }

    public function testRemoveLineOnValidatedThrows(): void
    {
        $receipt = $this->makeReceipt();
        $_       = $receipt->pullDomainEvents();

        $line = $this->makeLine();
        $receipt->addLine($line, new \DateTimeImmutable());
        $receipt->validate(new \DateTimeImmutable(), new \DateTimeImmutable());
        $_ = $receipt->pullDomainEvents();

        $this->expectException(ReceiptValidationException::class);
        $receipt->removeLine(SupplierReceiptLineId::fromString(self::LINE_ID_1), new \DateTimeImmutable());
    }

    public function testUpdateCommentOnValidatedThrows(): void
    {
        $receipt = $this->makeReceipt();
        $_       = $receipt->pullDomainEvents();

        $receipt->validate(new \DateTimeImmutable(), new \DateTimeImmutable());
        $_ = $receipt->pullDomainEvents();

        $this->expectException(ReceiptValidationException::class);
        $receipt->updateComment('new comment', new \DateTimeImmutable());
    }

    public function testUpdateCommentOnPendingReviewSucceeds(): void
    {
        $receipt = $this->makeReceipt();
        $_       = $receipt->pullDomainEvents();

        $receipt->updateComment('Important note', new \DateTimeImmutable());

        self::assertSame('Important note', $receipt->comment());
    }

    public function testDeliveryNoteReferenceRejectsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DeliveryNoteReference::fromString('');
    }

    public function testDeliveryNoteReferenceRejectsTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DeliveryNoteReference::fromString(str_repeat('A', 129));
    }

    public function testDeliveryNoteReferenceAcceptsValid(): void
    {
        $ref = DeliveryNoteReference::fromString('BL-2026-001');
        self::assertSame('BL-2026-001', $ref->toString());
    }

    private function makeReceipt(): SupplierReceipt
    {
        return SupplierReceipt::create(
            id: SupplierReceiptId::fromString(self::RECEIPT_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            supplierId: SupplierId::fromString(self::SUPPLIER_ID),
            purchaseOrderId: PurchaseOrderId::fromString(self::PO_ID),
            deliveryNoteReference: DeliveryNoteReference::fromString('BL-2026-001'),
            matchType: ReceiptMatchType::AUTO_MATCHED,
            receivedBy: 'Dr. Dupont',
            comment: null,
            createdAt: new \DateTimeImmutable('2026-01-10'),
        );
    }

    private function makeLine(): SupplierReceiptLine
    {
        return SupplierReceiptLine::create(
            id: SupplierReceiptLineId::fromString(self::LINE_ID_1),
            purchaseOrderLineId: PurchaseOrderLineId::fromString(self::PO_LINE_ID_1),
            articleId: ArticleId::fromString(self::ARTICLE_ID),
            receivedAmount: '5',
            receivedUnit: UnitOfMeasure::fromString('UNIT'),
        );
    }
}
