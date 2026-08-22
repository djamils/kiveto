<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Application\Command;

use App\Context\Billing\Application\Command\AddManualInvoiceLine\AddManualInvoiceLine;
use App\Context\Billing\Application\Command\AddManualInvoiceLine\AddManualInvoiceLineHandler;
use App\Context\Billing\Application\Command\ApplyInvoiceDiscount\ApplyInvoiceDiscount;
use App\Context\Billing\Application\Command\ApplyInvoiceDiscount\ApplyInvoiceDiscountHandler;
use App\Context\Billing\Application\Command\CancelInvoice\CancelInvoice;
use App\Context\Billing\Application\Command\CancelInvoice\CancelInvoiceHandler;
use App\Context\Billing\Application\Command\CheckoutAdmission\CheckoutAdmission;
use App\Context\Billing\Application\Command\CheckoutAdmission\CheckoutAdmissionHandler;
use App\Context\Billing\Application\Command\IssueInvoice\IssueInvoice;
use App\Context\Billing\Application\Command\IssueInvoice\IssueInvoiceHandler;
use App\Context\Billing\Application\Command\RecordInvoicePayment\RecordInvoicePayment;
use App\Context\Billing\Application\Command\RecordInvoicePayment\RecordInvoicePaymentHandler;
use App\Context\Billing\Application\Command\RemoveInvoiceLine\RemoveInvoiceLine;
use App\Context\Billing\Application\Command\RemoveInvoiceLine\RemoveInvoiceLineHandler;
use App\Context\Billing\Application\Port\AdmissionCoordinatorInterface;
use App\Context\Billing\Application\Port\ResolvedTaxDto;
use App\Context\Billing\Application\Port\TaxResolverInterface;
use App\Context\Billing\Domain\Exception\InvalidDiscountException;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\Service\InvoiceNumberGeneratorInterface;
use App\Context\Billing\Domain\ValueObject\AdmissionId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\DiscountKind;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use App\Context\Billing\Domain\ValueObject\InvoiceStatus;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class InvoiceCommandHandlersTest extends TestCase
{
    private const string CLINIC_ID    = '01960000-0000-7000-8000-000000000501';
    private const string INVOICE_ID   = '01960000-0000-7000-8000-000000000502';
    private const string ADMISSION_ID = '01960000-0000-7000-8000-000000000503';
    private const string LINE_ID      = '01960000-0000-7000-8000-000000000504';
    private const string NEW_ID       = '01960000-0000-7000-8000-000000000505';

    private InvoiceRepositoryInterface&MockObject $invoices;
    private ClockInterface&MockObject $clock;

    protected function setUp(): void
    {
        $this->invoices = $this->createMock(InvoiceRepositoryInterface::class);
        $this->clock    = $this->createMock(ClockInterface::class);
        $this->clock->method('now')->willReturn($this->now());
    }

    // ── Issue ──

    public function testIssuingAsksTheGeneratorForThisYearsNumber(): void
    {
        $invoice = $this->draftWithOneLine();
        $this->invoices->expects(self::once())->method('get')->willReturn($invoice);
        $this->invoices->expects(self::once())->method('save')->with($invoice);

        $numbers = $this->createMock(InvoiceNumberGeneratorInterface::class);
        $numbers->expects(self::once())
            ->method('next')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
                2026,
            )
            ->willReturn(InvoiceNumber::of(2026, 12))
        ;

        (new IssueInvoiceHandler($this->invoices, $numbers, $this->clock))(
            new IssueInvoice(self::CLINIC_ID, self::INVOICE_ID),
        );

        self::assertSame('FAC-2026-000012', $invoice->number()?->toString());
        self::assertSame(InvoiceStatus::ISSUED, $invoice->status());
    }

    // ── Record a payment ──

    public function testRecordingAPaymentAddsItToTheInvoice(): void
    {
        $invoice = $this->issuedInvoice();
        $this->invoices->expects(self::once())->method('get')->willReturn($invoice);
        $this->invoices->expects(self::once())->method('save')->with($invoice);

        $uuids = $this->createMock(UuidGeneratorInterface::class);
        $uuids->method('generate')->willReturn(self::NEW_ID);

        (new RecordInvoicePaymentHandler($this->invoices, $uuids, $this->clock))(
            new RecordInvoicePayment(
                clinicId: self::CLINIC_ID,
                invoiceId: self::INVOICE_ID,
                method: 'CHECK',
                amountMinorUnits: 2000,
                reference: 'CHQ-1',
                recordedByUserId: '01960000-0000-7000-8000-000000000601',
            ),
        );

        self::assertCount(1, $invoice->payments());
        self::assertSame(2000, $invoice->amountPaid()->minorUnits());
        self::assertSame('CHQ-1', $invoice->payments()[0]->reference());
        self::assertSame(InvoiceStatus::PARTIALLY_PAID, $invoice->status());
    }

    public function testAnUnknownPaymentMethodIsRejected(): void
    {
        $this->invoices->method('get')->willReturn($this->issuedInvoice());
        $uuids = $this->createMock(UuidGeneratorInterface::class);
        $uuids->method('generate')->willReturn(self::NEW_ID);

        $this->expectException(\ValueError::class);

        (new RecordInvoicePaymentHandler($this->invoices, $uuids, $this->clock))(
            new RecordInvoicePayment(self::CLINIC_ID, self::INVOICE_ID, 'BITCOIN', 100),
        );
    }

    // ── Cancel ──

    public function testCancellingPassesTheReasonThrough(): void
    {
        $invoice = $this->issuedInvoice();
        $this->invoices->expects(self::once())->method('get')->willReturn($invoice);
        $this->invoices->expects(self::once())->method('save')->with($invoice);

        (new CancelInvoiceHandler($this->invoices, $this->clock))(
            new CancelInvoice(self::CLINIC_ID, self::INVOICE_ID, 'Doublon'),
        );

        self::assertSame(InvoiceStatus::CANCELLED, $invoice->status());
        self::assertSame('Doublon', $invoice->cancellationReason());
    }

    // ── Remove a line ──

    public function testRemovingALineDropsItFromTheDraft(): void
    {
        $invoice = $this->draftWithOneLine();
        $this->invoices->expects(self::once())->method('get')->willReturn($invoice);
        $this->invoices->expects(self::once())->method('save')->with($invoice);

        (new RemoveInvoiceLineHandler($this->invoices, $this->clock))(
            new RemoveInvoiceLine(self::CLINIC_ID, self::INVOICE_ID, self::LINE_ID),
        );

        self::assertSame([], $invoice->lines());
    }

    // ── Discount ──

    public function testAPercentageDiscountIsApplied(): void
    {
        $invoice = $this->draftWithOneLine();
        $this->invoices->expects(self::once())->method('get')->willReturn($invoice);
        $this->invoices->expects(self::once())->method('save')->with($invoice);

        (new ApplyInvoiceDiscountHandler($this->invoices, $this->clock))(
            new ApplyInvoiceDiscount(self::CLINIC_ID, self::INVOICE_ID, 'PERCENTAGE', '10', 'Fidélité'),
        );

        $discount = $invoice->discount();
        self::assertNotNull($discount);
        self::assertSame(DiscountKind::PERCENTAGE, $discount->kind());
        self::assertSame(500, $invoice->discountAmount()->minorUnits());
        self::assertSame('Fidélité', $discount->reason());
    }

    public function testAFixedDiscountIsReadAsMinorUnits(): void
    {
        $invoice = $this->draftWithOneLine();
        $this->invoices->method('get')->willReturn($invoice);
        $this->invoices->expects(self::once())->method('save');

        (new ApplyInvoiceDiscountHandler($this->invoices, $this->clock))(
            new ApplyInvoiceDiscount(self::CLINIC_ID, self::INVOICE_ID, 'FIXED', '750'),
        );

        self::assertSame(750, $invoice->discountAmount()->minorUnits());
    }

    public function testANullKindClearsTheDiscount(): void
    {
        $invoice = $this->draftWithOneLine();
        $invoice->applyDiscount(
            \App\Context\Billing\Domain\ValueObject\Discount::percentage('10'),
            $this->now(),
        );
        $this->invoices->method('get')->willReturn($invoice);
        $this->invoices->expects(self::once())->method('save');

        (new ApplyInvoiceDiscountHandler($this->invoices, $this->clock))(
            new ApplyInvoiceDiscount(self::CLINIC_ID, self::INVOICE_ID),
        );

        self::assertNull($invoice->discount());
    }

    public function testADiscountWithoutAValueIsRejected(): void
    {
        $this->invoices->method('get')->willReturn($this->draftWithOneLine());

        $this->expectException(InvalidDiscountException::class);
        $this->expectExceptionMessage('A discount needs a value.');

        (new ApplyInvoiceDiscountHandler($this->invoices, $this->clock))(
            new ApplyInvoiceDiscount(self::CLINIC_ID, self::INVOICE_ID, 'PERCENTAGE'),
        );
    }

    public function testAFixedDiscountInEurosRatherThanCentsIsRejected(): void
    {
        $this->invoices->method('get')->willReturn($this->draftWithOneLine());

        $this->expectException(InvalidDiscountException::class);
        $this->expectExceptionMessage('expressed in minor units, got "7.50"');

        (new ApplyInvoiceDiscountHandler($this->invoices, $this->clock))(
            new ApplyInvoiceDiscount(self::CLINIC_ID, self::INVOICE_ID, 'FIXED', '7.50'),
        );
    }

    // ── Manual line ──

    public function testAManualLineIsTaxedLikeAnyOther(): void
    {
        $invoice = $this->draftWithOneLine();
        $this->invoices->expects(self::once())->method('get')->willReturn($invoice);
        $this->invoices->expects(self::once())->method('save')->with($invoice);

        $taxResolver = $this->createMock(TaxResolverInterface::class);
        $taxResolver->expects(self::once())
            ->method('resolve')
            ->with(3000, 'EUR', 'veterinary.consumable.medical', self::CLINIC_ID)
            ->willReturn(new ResolvedTaxDto(3000, 600, 3600, '20'))
        ;

        $uuids = $this->createMock(UuidGeneratorInterface::class);
        $uuids->method('generate')->willReturn(self::NEW_ID);

        (new AddManualInvoiceLineHandler($this->invoices, $taxResolver, $uuids, $this->clock))(
            new AddManualInvoiceLine(
                clinicId: self::CLINIC_ID,
                invoiceId: self::INVOICE_ID,
                label: 'Collerette',
                quantity: '2',
                unitPriceMinorUnits: 1500,
                taxCategoryCode: 'veterinary.consumable.medical',
            ),
        );

        self::assertCount(2, $invoice->lines());
        self::assertSame(InvoiceLineSource::MANUAL, $invoice->lines()[1]->source());
        self::assertSame(3600, $invoice->lines()[1]->grossAmount()->minorUnits());
    }

    // ── Checkout ──

    public function testCheckoutIssuesPaysAndClosesTheVisit(): void
    {
        $invoice = $this->draftWithOneLine();
        $this->invoices->expects(self::once())->method('get')->willReturn($invoice);
        $this->invoices->expects(self::once())->method('save')->with($invoice);

        $numbers = $this->createMock(InvoiceNumberGeneratorInterface::class);
        $numbers->expects(self::once())->method('next')->willReturn(InvoiceNumber::of(2026, 5));

        $admissions = $this->createMock(AdmissionCoordinatorInterface::class);
        $admissions->expects(self::once())
            ->method('closeAfterCheckout')
            ->with(self::ADMISSION_ID, self::CLINIC_ID)
        ;

        $uuids = $this->createMock(UuidGeneratorInterface::class);
        $uuids->method('generate')->willReturn(self::NEW_ID);

        (new CheckoutAdmissionHandler($this->invoices, $numbers, $admissions, $uuids, $this->clock))(
            new CheckoutAdmission(self::CLINIC_ID, self::INVOICE_ID, 'CASH', 6000),
        );

        self::assertSame(InvoiceStatus::PAID, $invoice->status());
        self::assertSame('FAC-2026-000005', $invoice->number()?->toString());
    }

    public function testAPartialCheckoutLeavesTheVisitOpen(): void
    {
        $invoice = $this->draftWithOneLine();
        $this->invoices->method('get')->willReturn($invoice);
        $this->invoices->expects(self::once())->method('save');

        $numbers = $this->createMock(InvoiceNumberGeneratorInterface::class);
        $numbers->method('next')->willReturn(InvoiceNumber::of(2026, 5));

        $admissions = $this->createMock(AdmissionCoordinatorInterface::class);
        $admissions->expects(self::never())->method('closeAfterCheckout');

        $uuids = $this->createMock(UuidGeneratorInterface::class);
        $uuids->method('generate')->willReturn(self::NEW_ID);

        (new CheckoutAdmissionHandler($this->invoices, $numbers, $admissions, $uuids, $this->clock))(
            new CheckoutAdmission(self::CLINIC_ID, self::INVOICE_ID, 'CARD', 2000),
        );

        self::assertSame(InvoiceStatus::PARTIALLY_PAID, $invoice->status());
    }

    public function testAnAlreadyIssuedInvoiceIsNotNumberedAgain(): void
    {
        $invoice = $this->issuedInvoice();
        $this->invoices->method('get')->willReturn($invoice);
        $this->invoices->expects(self::once())->method('save');

        $numbers = $this->createMock(InvoiceNumberGeneratorInterface::class);
        $numbers->expects(self::never())->method('next');

        $admissions = $this->createMock(AdmissionCoordinatorInterface::class);
        $admissions->expects(self::once())->method('closeAfterCheckout');

        $uuids = $this->createMock(UuidGeneratorInterface::class);
        $uuids->method('generate')->willReturn(self::NEW_ID);

        (new CheckoutAdmissionHandler($this->invoices, $numbers, $admissions, $uuids, $this->clock))(
            new CheckoutAdmission(self::CLINIC_ID, self::INVOICE_ID, 'CARD', 6000),
        );

        self::assertSame('FAC-2026-000001', $invoice->number()?->toString());
    }

    public function testAZeroAmountCheckoutOnlyIssuesTheInvoice(): void
    {
        $invoice = $this->draftWithOneLine();
        $this->invoices->method('get')->willReturn($invoice);
        $this->invoices->expects(self::once())->method('save');

        $numbers = $this->createMock(InvoiceNumberGeneratorInterface::class);
        $numbers->method('next')->willReturn(InvoiceNumber::of(2026, 9));

        $admissions = $this->createMock(AdmissionCoordinatorInterface::class);
        $admissions->expects(self::never())->method('closeAfterCheckout');

        $uuids = $this->createMock(UuidGeneratorInterface::class);
        $uuids->method('generate')->willReturn(self::NEW_ID);

        (new CheckoutAdmissionHandler($this->invoices, $numbers, $admissions, $uuids, $this->clock))(
            new CheckoutAdmission(self::CLINIC_ID, self::INVOICE_ID, 'CASH', 0),
        );

        self::assertSame(InvoiceStatus::ISSUED, $invoice->status());
        self::assertSame([], $invoice->payments());
    }

    public function testAnInvoiceWithoutAVisitClosesNothing(): void
    {
        $invoice = Invoice::draft(
            id: InvoiceId::fromString(self::INVOICE_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            admissionId: null,
            payer: Payer::anonymous(),
            currency: CurrencyCode::fromString('EUR'),
            now: $this->now(),
        );
        $invoice->addLine($this->line(), $this->now());

        $this->invoices->method('get')->willReturn($invoice);
        $this->invoices->expects(self::once())->method('save');

        $numbers = $this->createMock(InvoiceNumberGeneratorInterface::class);
        $numbers->method('next')->willReturn(InvoiceNumber::of(2026, 11));

        $admissions = $this->createMock(AdmissionCoordinatorInterface::class);
        $admissions->expects(self::never())->method('closeAfterCheckout');

        $uuids = $this->createMock(UuidGeneratorInterface::class);
        $uuids->method('generate')->willReturn(self::NEW_ID);

        (new CheckoutAdmissionHandler($this->invoices, $numbers, $admissions, $uuids, $this->clock))(
            new CheckoutAdmission(self::CLINIC_ID, self::INVOICE_ID, 'CASH', 6000),
        );

        self::assertSame(InvoiceStatus::PAID, $invoice->status());
    }

    private function draftWithOneLine(): Invoice
    {
        $invoice = Invoice::draft(
            id: InvoiceId::fromString(self::INVOICE_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            admissionId: AdmissionId::fromString(self::ADMISSION_ID),
            payer: Payer::anonymous(),
            currency: CurrencyCode::fromString('EUR'),
            now: $this->now(),
        );

        $invoice->addLine($this->line(), $this->now());

        return $invoice;
    }

    private function issuedInvoice(): Invoice
    {
        $invoice = $this->draftWithOneLine();
        $invoice->issue(InvoiceNumber::of(2026, 1), $this->now());

        return $invoice;
    }

    private function line(): InvoiceLine
    {
        $eur = CurrencyCode::fromString('EUR');

        return InvoiceLine::create(
            id: InvoiceLineId::fromString(self::LINE_ID),
            label: 'Consultation standard',
            code: 'CONS_STD',
            quantity: '1',
            unitNetPrice: Money::fromMinorUnits(5000, $eur),
            taxCategoryCode: 'veterinary.act.consultation',
            taxRatePercent: '20',
            netAmount: Money::fromMinorUnits(5000, $eur),
            taxAmount: Money::fromMinorUnits(1000, $eur),
            grossAmount: Money::fromMinorUnits(6000, $eur),
            source: InvoiceLineSource::CONSULTATION,
        );
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-08-22 10:00:00');
    }
}
