<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Domain\PurchaseOrder;

use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderCreated;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderLineAdded;
use App\Context\Procurement\Domain\PurchaseOrder\Exception\EmptyPurchaseOrderException;
use App\Context\Procurement\Domain\PurchaseOrder\Exception\InvalidPurchaseOrderStatusTransitionException;
use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderNumber;
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

final class PurchaseOrderTest extends TestCase
{
    private const string PO_ID            = '01932b00-0000-7000-8000-000000000100';
    private const string CLINIC_ID        = '01932b00-0000-7000-8000-000000000003';
    private const string SUPPLIER_ID      = '01932b00-0000-7000-8000-000000000001';
    private const string ACCOUNT_ID       = '01932b00-0000-7000-8000-000000000002';
    private const string LINE_ID_1        = '01932b00-0000-7000-8000-000000000101';
    private const string ARTICLE_ID       = '01932b00-0000-7000-8000-000000000200';
    private const string CATALOG_ENTRY_ID = '01932b00-0000-7000-8000-000000000300';

    public function testCreateRaisesPurchaseOrderCreated(): void
    {
        $po     = $this->makePurchaseOrder();
        $events = $po->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(PurchaseOrderCreated::class, $events[0]);
        self::assertSame(self::PO_ID, $events[0]->aggregateId());
    }

    public function testAddLineOnDraftRaisesPurchaseOrderLineAdded(): void
    {
        $po = $this->makePurchaseOrder();
        $_  = $po->pullDomainEvents();

        $po->addLine(
            lineId: PurchaseOrderLineId::fromString(self::LINE_ID_1),
            articleId: ArticleId::fromString(self::ARTICLE_ID),
            catalogEntryId: SupplierCatalogEntryId::fromString(self::CATALOG_ENTRY_ID),
            orderedAmount: '10',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: $this->makeMoney(),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );

        $events = $po->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PurchaseOrderLineAdded::class, $events[0]);
        self::assertCount(1, $po->lines());
    }

    public function testAddLineOnNonDraftThrows(): void
    {
        $po = $this->makePurchaseOrder();
        $_  = $po->pullDomainEvents();

        // Add a line first so we can submit
        $po->addLine(
            lineId: PurchaseOrderLineId::fromString(self::LINE_ID_1),
            articleId: ArticleId::fromString(self::ARTICLE_ID),
            catalogEntryId: SupplierCatalogEntryId::fromString(self::CATALOG_ENTRY_ID),
            orderedAmount: '5',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: $this->makeMoney(),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );
        $po->markAsSubmitting(new \DateTimeImmutable());
        $_ = $po->pullDomainEvents();

        $this->expectException(InvalidPurchaseOrderStatusTransitionException::class);
        $po->addLine(
            lineId: PurchaseOrderLineId::fromString('01932b00-0000-7000-8000-000000000102'),
            articleId: ArticleId::fromString(self::ARTICLE_ID),
            catalogEntryId: SupplierCatalogEntryId::fromString(self::CATALOG_ENTRY_ID),
            orderedAmount: '1',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: $this->makeMoney(),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );
    }

    public function testUpdateLineOnNonDraftThrows(): void
    {
        $po = $this->makePurchaseOrder();
        $_  = $po->pullDomainEvents();

        $lineId = PurchaseOrderLineId::fromString(self::LINE_ID_1);
        $po->addLine(
            lineId: $lineId,
            articleId: ArticleId::fromString(self::ARTICLE_ID),
            catalogEntryId: SupplierCatalogEntryId::fromString(self::CATALOG_ENTRY_ID),
            orderedAmount: '5',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: $this->makeMoney(),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );
        $po->markAsSubmitting(new \DateTimeImmutable());
        $_ = $po->pullDomainEvents();

        $this->expectException(InvalidPurchaseOrderStatusTransitionException::class);
        $po->updateLine(
            lineId: $lineId,
            orderedAmount: '10',
            unitPrice: $this->makeMoney(2000),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );
    }

    public function testRemoveLineOnNonDraftThrows(): void
    {
        $po = $this->makePurchaseOrder();
        $_  = $po->pullDomainEvents();

        $lineId = PurchaseOrderLineId::fromString(self::LINE_ID_1);
        $po->addLine(
            lineId: $lineId,
            articleId: ArticleId::fromString(self::ARTICLE_ID),
            catalogEntryId: SupplierCatalogEntryId::fromString(self::CATALOG_ENTRY_ID),
            orderedAmount: '5',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: $this->makeMoney(),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );
        $po->markAsSubmitting(new \DateTimeImmutable());
        $_ = $po->pullDomainEvents();

        $this->expectException(InvalidPurchaseOrderStatusTransitionException::class);
        $po->removeLine($lineId, new \DateTimeImmutable());
    }

    public function testMarkAsSubmittingWithEmptyLinesThrowsEmptyPurchaseOrderException(): void
    {
        $po = $this->makePurchaseOrder();
        $_  = $po->pullDomainEvents();

        $this->expectException(EmptyPurchaseOrderException::class);
        $po->markAsSubmitting(new \DateTimeImmutable());
    }

    public function testMarkAsSubmittingOnNonDraftThrows(): void
    {
        $po = $this->makePurchaseOrder();
        $_  = $po->pullDomainEvents();

        $po->addLine(
            lineId: PurchaseOrderLineId::fromString(self::LINE_ID_1),
            articleId: ArticleId::fromString(self::ARTICLE_ID),
            catalogEntryId: SupplierCatalogEntryId::fromString(self::CATALOG_ENTRY_ID),
            orderedAmount: '1',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: $this->makeMoney(),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );
        $po->markAsSubmitting(new \DateTimeImmutable());
        $_ = $po->pullDomainEvents();

        $this->expectException(InvalidPurchaseOrderStatusTransitionException::class);
        $po->markAsSubmitting(new \DateTimeImmutable());
    }

    public function testPurchaseOrderNumberValidFormat(): void
    {
        $number = PurchaseOrderNumber::fromString('PO-2026-000001');
        self::assertSame('PO-2026-000001', $number->toString());
    }

    public function testPurchaseOrderNumberInvalidFormatThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PurchaseOrderNumber::fromString('INVALID-FORMAT');
    }

    public function testTotalAmountSkipsCancelledLines(): void
    {
        $po = $this->makePurchaseOrder();
        $_  = $po->pullDomainEvents();

        $lineId = PurchaseOrderLineId::fromString(self::LINE_ID_1);
        $po->addLine(
            lineId: $lineId,
            articleId: ArticleId::fromString(self::ARTICLE_ID),
            catalogEntryId: SupplierCatalogEntryId::fromString(self::CATALOG_ENTRY_ID),
            orderedAmount: '10',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: $this->makeMoney(100),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );

        // Add a second line that will be cancelled
        $cancelledLineId = PurchaseOrderLineId::fromString('01932b00-0000-7000-8000-000000000102');
        $po->addLine(
            lineId: $cancelledLineId,
            articleId: ArticleId::fromString(self::ARTICLE_ID),
            catalogEntryId: SupplierCatalogEntryId::fromString(self::CATALOG_ENTRY_ID),
            orderedAmount: '20',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: $this->makeMoney(500),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );

        // Move to SUBMITTED so we can cancel the line
        $po->markAsSubmitting(new \DateTimeImmutable());
        $po->submit(
            \App\Context\Procurement\Domain\PurchaseOrder\ValueObject\ExternalReference::create('EXT-001', 'test'),
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
        );
        $po->cancelLine($cancelledLineId, 'no longer needed', new \DateTimeImmutable());
        $_ = $po->pullDomainEvents();

        // Total should be 10 * 100 = 1000 minor units (cancelled line skipped)
        self::assertSame(1000, $po->totalAmount()->minorUnits());
    }

    public function testMarkAsSendingFailedOnNonSubmittingThrows(): void
    {
        $po = $this->makePurchaseOrder();
        $_  = $po->pullDomainEvents();

        // DRAFT — not SUBMITTING
        $this->expectException(InvalidPurchaseOrderStatusTransitionException::class);
        $po->markAsSendingFailed('boom', new \DateTimeImmutable());
    }

    public function testRecordFullReceptionOnInvalidStatusThrows(): void
    {
        $po = $this->makePurchaseOrder();
        $_  = $po->pullDomainEvents();

        // DRAFT — recordFullReception requires CONFIRMED or PARTIALLY_RECEIVED
        $this->expectException(InvalidPurchaseOrderStatusTransitionException::class);
        $po->recordFullReception([], new \DateTimeImmutable());
    }

    public function testDaysSinceSubmissionReturnsZeroWhenNotSubmitted(): void
    {
        $po = $this->makePurchaseOrder();
        $_  = $po->pullDomainEvents();

        self::assertSame(0, $po->daysSinceSubmission());
    }

    public function testDaysSinceSubmissionReturnsDaysAfterSubmission(): void
    {
        $po = $this->makePurchaseOrder();
        $_  = $po->pullDomainEvents();

        $po->addLine(
            lineId: PurchaseOrderLineId::fromString(self::LINE_ID_1),
            articleId: ArticleId::fromString(self::ARTICLE_ID),
            catalogEntryId: SupplierCatalogEntryId::fromString(self::CATALOG_ENTRY_ID),
            orderedAmount: '5',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: $this->makeMoney(),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );
        $po->markAsSubmitting(new \DateTimeImmutable());
        $po->submit(
            \App\Context\Procurement\Domain\PurchaseOrder\ValueObject\ExternalReference::create('EXT-001', 'test'),
            new \DateTimeImmutable('-3 days'),
            new \DateTimeImmutable(),
        );
        $_ = $po->pullDomainEvents();

        self::assertGreaterThanOrEqual(2, $po->daysSinceSubmission());
    }

    public function testCancelUnknownLineThrowsRuntimeException(): void
    {
        $po = $this->makePurchaseOrder();
        $_  = $po->pullDomainEvents();

        $po->addLine(
            lineId: PurchaseOrderLineId::fromString(self::LINE_ID_1),
            articleId: ArticleId::fromString(self::ARTICLE_ID),
            catalogEntryId: SupplierCatalogEntryId::fromString(self::CATALOG_ENTRY_ID),
            orderedAmount: '1',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: $this->makeMoney(),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );
        $po->markAsSubmitting(new \DateTimeImmutable());
        $po->submit(
            \App\Context\Procurement\Domain\PurchaseOrder\ValueObject\ExternalReference::create('EXT-001', 'test'),
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
        );
        $_ = $po->pullDomainEvents();

        $this->expectException(\RuntimeException::class);
        $po->cancelLine(
            PurchaseOrderLineId::fromString('01932b00-0000-7000-8000-000000000999'),
            'reason',
            new \DateTimeImmutable(),
        );
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
}
