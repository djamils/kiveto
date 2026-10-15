<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Domain;

use App\Context\Billing\Domain\Event\InvoiceCancelled;
use App\Context\Billing\Domain\Event\InvoiceDrafted;
use App\Context\Billing\Domain\Event\InvoiceIssued;
use App\Context\Billing\Domain\Event\InvoicePaid;
use App\Context\Billing\Domain\Event\PaymentRecorded;
use App\Context\Billing\Domain\Exception\CurrencyMismatchException;
use App\Context\Billing\Domain\Exception\InvoiceCannotBeCancelledException;
use App\Context\Billing\Domain\Exception\InvoiceHasNoLinesException;
use App\Context\Billing\Domain\Exception\InvoiceNotEditableException;
use App\Context\Billing\Domain\Exception\InvoiceNotPayableException;
use App\Context\Billing\Domain\Exception\InvoiceRequiresIdentifiedPayerException;
use App\Context\Billing\Domain\Exception\PaymentExceedsBalanceException;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\ValueObject\AdmissionId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\Discount;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use App\Context\Billing\Domain\ValueObject\InvoiceStatus;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PayerId;
use App\Context\Billing\Domain\ValueObject\Payment;
use App\Context\Billing\Domain\ValueObject\PaymentId;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class InvoiceTest extends TestCase
{
    private const string INVOICE_ID   = '01960000-0000-7000-8000-0000000000a1';
    private const string CLINIC_ID    = '01960000-0000-7000-8000-0000000000a2';
    private const string ADMISSION_ID = '01960000-0000-7000-8000-0000000000a3';
    private const string CLIENT_ID    = '01960000-0000-7000-8000-0000000000a4';

    public function testADraftedInvoiceRecordsItsCreation(): void
    {
        $invoice = $this->draft();

        self::assertSame(InvoiceStatus::DRAFT, $invoice->status());
        self::assertNull($invoice->number());
        self::assertSame(self::ADMISSION_ID, $invoice->admissionId()?->toString());
        self::assertSame('Sophie Dupont', $invoice->payer()->displayName());
        self::assertSame('EUR', $invoice->currency()->toString());
        self::assertTrue($invoice->totalGross()->isZero());

        $events = $invoice->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(InvoiceDrafted::class, $events[0]);
    }

    public function testLinesAddUpToTheTotals(): void
    {
        $invoice = $this->draft();
        $invoice->addLine($this->line(5000, 1000, 6000), $this->now());
        $invoice->addLine($this->line(2000, 400, 2400), $this->now());

        self::assertSame(7000, $invoice->totalNet()->minorUnits());
        self::assertSame(1400, $invoice->totalTax()->minorUnits());
        self::assertSame(8400, $invoice->totalGross()->minorUnits());
        self::assertSame(8400, $invoice->balanceDue()->minorUnits());
        self::assertCount(2, $invoice->lines());
    }

    public function testALineInAnotherCurrencyIsRefused(): void
    {
        $invoice = $this->draft();
        $usd     = CurrencyCode::fromString('USD');

        $this->expectException(CurrencyMismatchException::class);
        $this->expectExceptionMessage('the invoice is in EUR, got USD');

        $invoice->addLine(
            InvoiceLine::create(
                id: InvoiceLineId::fromString('01960000-0000-7000-8000-0000000000b9'),
                label: 'Consultation',
                code: null,
                quantity: '1',
                unitNetPrice: Money::fromMinorUnits(5000, $usd),
                taxCategoryCode: null,
                taxRatePercent: '20',
                netAmount: Money::fromMinorUnits(5000, $usd),
                taxAmount: Money::fromMinorUnits(1000, $usd),
                grossAmount: Money::fromMinorUnits(6000, $usd),
                source: InvoiceLineSource::MANUAL,
            ),
            $this->now(),
        );
    }

    public function testALineCanBeRemovedWhileTheInvoiceIsADraft(): void
    {
        $invoice = $this->draft();
        $line    = $this->line(5000, 1000, 6000);
        $invoice->addLine($line, $this->now());
        $invoice->addLine($this->line(2000, 400, 2400, '01960000-0000-7000-8000-0000000000c1'), $this->now());

        $invoice->removeLine($line->id(), $this->now());

        self::assertCount(1, $invoice->lines());
        self::assertSame(2400, $invoice->totalGross()->minorUnits());
    }

    public function testIssuingTakesANumberAndFreezesTheInvoice(): void
    {
        $invoice = $this->draft();
        $invoice->addLine($this->line(5000, 1000, 6000), $this->now());
        (void) $invoice->pullDomainEvents();

        $invoice->issue(InvoiceNumber::of(2026, 42), $this->now());

        self::assertSame(InvoiceStatus::ISSUED, $invoice->status());
        self::assertSame('FAC-2026-000042', $invoice->number()?->toString());
        self::assertSame($this->now()->format('Y-m-d H:i:s'), $invoice->issuedAt()?->format('Y-m-d H:i:s'));

        $events = $invoice->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(InvoiceIssued::class, $events[0]);
    }

    public function testTheInvoiceOfAVisitWithoutAnIdentifiedClientIsNotIssued(): void
    {
        $invoice = $this->draft();
        $invoice->addLine($this->line(5000, 1000, 6000), $this->now());
        $invoice->changePayer(Payer::anonymous(), $this->now());

        $this->expectException(InvoiceRequiresIdentifiedPayerException::class);
        $this->expectExceptionMessage('without an identified client');

        $invoice->issue(InvoiceNumber::of(2026, 43), $this->now());
    }

    public function testACounterSaleToAPasserByIsIssued(): void
    {
        $invoice = Invoice::draft(
            id: InvoiceId::fromString(self::INVOICE_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            admissionId: null,
            payer: Payer::anonymous(),
            currency: CurrencyCode::fromString('EUR'),
            now: $this->now(),
        );
        $invoice->addLine($this->line(5000, 1000, 6000), $this->now());

        $invoice->issue(InvoiceNumber::of(2026, 44), $this->now());

        self::assertSame(InvoiceStatus::ISSUED, $invoice->status());
    }

    public function testAnIssuedInvoiceCanNoLongerBeEdited(): void
    {
        $invoice = $this->issued();

        $this->expectException(InvoiceNotEditableException::class);
        $this->expectExceptionMessage('can no longer be edited: it is ISSUED');

        $invoice->addLine($this->line(1000, 200, 1200, '01960000-0000-7000-8000-0000000000c2'), $this->now());
    }

    public function testADiscountCannotBeAppliedOnceIssued(): void
    {
        $invoice = $this->issued();

        $this->expectException(InvoiceNotEditableException::class);

        $invoice->applyDiscount(Discount::percentage('10'), $this->now());
    }

    public function testThePayerCannotChangeOnceIssued(): void
    {
        $invoice = $this->issued();

        $this->expectException(InvoiceNotEditableException::class);

        $invoice->changePayer(Payer::anonymous(), $this->now());
    }

    public function testNotesCannotChangeOnceIssued(): void
    {
        $invoice = $this->issued();

        $this->expectException(InvoiceNotEditableException::class);

        $invoice->setNotes('Trop tard', $this->now());
    }

    public function testAnEmptyInvoiceCannotBeIssued(): void
    {
        $invoice = $this->draft();

        $this->expectException(InvoiceHasNoLinesException::class);
        $this->expectExceptionMessage('cannot be issued without a single line');

        $invoice->issue(InvoiceNumber::of(2026, 1), $this->now());
    }

    public function testAPaymentThatSettlesTheInvoiceMarksItPaid(): void
    {
        $invoice = $this->issued();
        (void) $invoice->pullDomainEvents();

        $invoice->recordPayment($this->payment(6000), $this->now());

        self::assertSame(InvoiceStatus::PAID, $invoice->status());
        self::assertSame(0, $invoice->balanceDue()->minorUnits());
        self::assertSame(6000, $invoice->amountPaid()->minorUnits());
        self::assertSame($this->now()->format('Y-m-d H:i:s'), $invoice->paidAt()?->format('Y-m-d H:i:s'));

        $events = $invoice->pullDomainEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(PaymentRecorded::class, $events[0]);
        self::assertInstanceOf(InvoicePaid::class, $events[1]);
    }

    public function testAPartialPaymentLeavesABalance(): void
    {
        $invoice = $this->issued();

        $invoice->recordPayment($this->payment(2000), $this->now());

        self::assertSame(InvoiceStatus::PARTIALLY_PAID, $invoice->status());
        self::assertSame(4000, $invoice->balanceDue()->minorUnits());
        self::assertNull($invoice->paidAt());
    }

    public function testASecondPaymentCanSettleTheRest(): void
    {
        $invoice = $this->issued();
        $invoice->recordPayment($this->payment(2000), $this->now());
        $invoice->recordPayment($this->payment(4000, '01960000-0000-7000-8000-0000000000d2'), $this->now());

        self::assertSame(InvoiceStatus::PAID, $invoice->status());
        self::assertCount(2, $invoice->payments());
    }

    public function testAPaymentAboveTheBalanceIsRefused(): void
    {
        $invoice = $this->issued();

        $this->expectException(PaymentExceedsBalanceException::class);
        $this->expectExceptionMessage('Payment of 9000 exceeds the balance due of 6000.');

        $invoice->recordPayment($this->payment(9000), $this->now());
    }

    public function testAPaymentInAnotherCurrencyIsRefused(): void
    {
        $invoice = $this->issued();

        $this->expectException(CurrencyMismatchException::class);

        $invoice->recordPayment(
            Payment::create(
                id: PaymentId::fromString('01960000-0000-7000-8000-0000000000d3'),
                method: PaymentMethod::CARD,
                amount: Money::fromMinorUnits(1000, CurrencyCode::fromString('USD')),
                receivedAt: $this->now(),
            ),
            $this->now(),
        );
    }

    public function testADraftCannotTakeAPayment(): void
    {
        $invoice = $this->draft();

        $this->expectException(InvoiceNotPayableException::class);
        $this->expectExceptionMessage('cannot take a payment while it is DRAFT');

        $invoice->recordPayment($this->payment(1000), $this->now());
    }

    public function testAPaidInvoiceTakesNoFurtherPayment(): void
    {
        $invoice = $this->issued();
        $invoice->recordPayment($this->payment(6000), $this->now());

        $this->expectException(InvoiceNotPayableException::class);

        $invoice->recordPayment($this->payment(100, '01960000-0000-7000-8000-0000000000d4'), $this->now());
    }

    public function testAFullyDiscountedInvoiceIsPaidTheMomentItIsIssued(): void
    {
        $invoice = $this->draft();
        $invoice->addLine($this->line(5000, 1000, 6000), $this->now());
        $invoice->applyDiscount(Discount::percentage('100', 'Geste commercial'), $this->now());
        (void) $invoice->pullDomainEvents();

        $invoice->issue(InvoiceNumber::of(2026, 7), $this->now());

        self::assertSame(InvoiceStatus::PAID, $invoice->status());
        self::assertSame(0, $invoice->totalGross()->minorUnits());

        $events = $invoice->pullDomainEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(InvoiceIssued::class, $events[0]);
        self::assertInstanceOf(InvoicePaid::class, $events[1]);
    }

    public function testAPercentageDiscountScalesTheTaxToo(): void
    {
        $invoice = $this->draft();
        $invoice->addLine($this->line(5000, 1000, 6000), $this->now());
        $invoice->applyDiscount(Discount::percentage('10'), $this->now());

        self::assertSame(5000, $invoice->totalNetBeforeDiscount()->minorUnits());
        self::assertSame(500, $invoice->discountAmount()->minorUnits());
        self::assertSame(4500, $invoice->totalNet()->minorUnits());
        self::assertSame(900, $invoice->totalTax()->minorUnits());
        self::assertSame(5400, $invoice->totalGross()->minorUnits());
    }

    public function testAFixedDiscountNeverExceedsTheNetTotal(): void
    {
        $invoice = $this->draft();
        $invoice->addLine($this->line(5000, 1000, 6000), $this->now());
        $invoice->applyDiscount(
            Discount::fixed(Money::fromMinorUnits(9999, CurrencyCode::fromString('EUR'))),
            $this->now(),
        );

        self::assertSame(5000, $invoice->discountAmount()->minorUnits());
        self::assertSame(0, $invoice->totalNet()->minorUnits());
        self::assertSame(0, $invoice->totalTax()->minorUnits());
    }

    public function testClearingTheDiscountRestoresTheTotals(): void
    {
        $invoice = $this->draft();
        $invoice->addLine($this->line(5000, 1000, 6000), $this->now());
        $invoice->applyDiscount(Discount::percentage('10'), $this->now());
        $invoice->applyDiscount(null, $this->now());

        self::assertNull($invoice->discount());
        self::assertSame(0, $invoice->discountAmount()->minorUnits());
        self::assertSame(6000, $invoice->totalGross()->minorUnits());
    }

    public function testADiscountOnAnEmptyInvoiceTakesNothingOff(): void
    {
        $invoice = $this->draft();
        $invoice->applyDiscount(Discount::percentage('50'), $this->now());

        self::assertSame(0, $invoice->discountAmount()->minorUnits());
        self::assertSame(0, $invoice->totalTax()->minorUnits());
    }

    public function testTheTaxBreakdownGroupsLinesByRate(): void
    {
        $invoice = $this->draft();
        $invoice->addLine($this->line(5000, 1000, 6000), $this->now());
        $invoice->addLine($this->line(2000, 400, 2400, '01960000-0000-7000-8000-0000000000c3'), $this->now());
        $invoice->addLine(
            $this->line(1000, 55, 1055, '01960000-0000-7000-8000-0000000000c4', '5.5'),
            $this->now(),
        );

        $breakdown = $invoice->taxBreakdown();

        self::assertCount(2, $breakdown);
        // Highest rate first: that is the order an invoice prints them in.
        self::assertSame('20', $breakdown[0]['ratePercent']);
        self::assertSame(7000, $breakdown[0]['netMinorUnits']);
        self::assertSame(1400, $breakdown[0]['taxMinorUnits']);
        self::assertSame('5.5', $breakdown[1]['ratePercent']);
        self::assertSame(1000, $breakdown[1]['netMinorUnits']);
    }

    public function testCancellingRecordsTheReason(): void
    {
        $invoice = $this->issued();
        (void) $invoice->pullDomainEvents();

        $invoice->cancel('Erreur de saisie', $this->now());

        self::assertSame(InvoiceStatus::CANCELLED, $invoice->status());
        self::assertSame('Erreur de saisie', $invoice->cancellationReason());
        self::assertSame($this->now()->format('Y-m-d H:i:s'), $invoice->cancelledAt()?->format('Y-m-d H:i:s'));

        $events = $invoice->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(InvoiceCancelled::class, $events[0]);
    }

    public function testAnInvoiceWithAPaymentCannotBeCancelled(): void
    {
        $invoice = $this->issued();
        $invoice->recordPayment($this->payment(1000), $this->now());

        $this->expectException(InvoiceCannotBeCancelledException::class);
        $this->expectExceptionMessage('a payment was already recorded against it');

        $invoice->cancel('Erreur', $this->now());
    }

    public function testAnAlreadyCancelledInvoiceCannotBeCancelledTwice(): void
    {
        $invoice = $this->issued();
        $invoice->cancel('Erreur', $this->now());

        $this->expectException(InvoiceCannotBeCancelledException::class);
        $this->expectExceptionMessage('it is already cancelled');

        $invoice->cancel('Encore', $this->now());
    }

    public function testCancellingNeedsAReason(): void
    {
        $invoice = $this->issued();

        $this->expectException(InvoiceCannotBeCancelledException::class);
        $this->expectExceptionMessage('a reason is required');

        $invoice->cancel('   ', $this->now());
    }

    public function testNotesAreTrimmedAndEmptiedToNull(): void
    {
        $invoice = $this->draft();

        $invoice->setNotes('  Merci de votre visite  ', $this->now());
        self::assertSame('Merci de votre visite', $invoice->notes());

        $invoice->setNotes('   ', $this->now());
        self::assertNull($invoice->notes());

        $invoice->setNotes(null, $this->now());
        self::assertNull($invoice->notes());
    }

    public function testThePayerCanBeChangedWhileADraft(): void
    {
        $invoice = $this->draft();

        $invoice->changePayer(Payer::anonymous(), $this->now());

        self::assertSame('Client de passage', $invoice->payer()->displayName());
    }

    public function testAnInvoiceIsReconstitutedWithEverythingItHeld(): void
    {
        $invoice = Invoice::reconstitute(
            id: InvoiceId::fromString(self::INVOICE_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            number: InvoiceNumber::of(2026, 3),
            status: InvoiceStatus::PARTIALLY_PAID,
            admissionId: AdmissionId::fromString(self::ADMISSION_ID),
            payer: Payer::create(PayerId::fromString(self::CLIENT_ID), 'Sophie Dupont', ['1 rue des Lilas']),
            lines: [$this->line(5000, 1000, 6000)],
            payments: [$this->payment(2000)],
            discount: Discount::percentage('5'),
            currency: CurrencyCode::fromString('EUR'),
            notes: 'Reste à régler',
            createdAt: $this->now(),
            updatedAt: $this->now(),
            issuedAt: $this->now(),
            paidAt: null,
            cancelledAt: null,
            cancellationReason: null,
        );

        self::assertSame('FAC-2026-000003', $invoice->number()?->toString());
        self::assertSame(self::CLINIC_ID, $invoice->clinicId()->toString());
        self::assertSame('Reste à régler', $invoice->notes());
        self::assertSame('5', $invoice->discount()?->value());
        self::assertSame(2000, $invoice->amountPaid()->minorUnits());
        self::assertSame($this->now()->format('Y-m-d H:i:s'), $invoice->createdAt()->format('Y-m-d H:i:s'));
        self::assertSame($this->now()->format('Y-m-d H:i:s'), $invoice->updatedAt()->format('Y-m-d H:i:s'));
        self::assertSame(self::INVOICE_ID, $invoice->id()->toString());
        self::assertCount(1, $invoice->lines());
        self::assertSame(['1 rue des Lilas'], $invoice->payer()->addressLines());
        self::assertNull($invoice->cancelledAt());
        self::assertNull($invoice->cancellationReason());
    }

    private function draft(): Invoice
    {
        return Invoice::draft(
            id: InvoiceId::fromString(self::INVOICE_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            admissionId: AdmissionId::fromString(self::ADMISSION_ID),
            payer: Payer::create(PayerId::fromString(self::CLIENT_ID), 'Sophie Dupont'),
            currency: CurrencyCode::fromString('EUR'),
            now: $this->now(),
        );
    }

    private function issued(): Invoice
    {
        $invoice = $this->draft();
        $invoice->addLine($this->line(5000, 1000, 6000), $this->now());
        $invoice->issue(InvoiceNumber::of(2026, 1), $this->now());

        return $invoice;
    }

    private function line(
        int $net,
        int $tax,
        int $gross,
        string $id = '01960000-0000-7000-8000-0000000000b1',
        string $rate = '20',
    ): InvoiceLine {
        $eur = CurrencyCode::fromString('EUR');

        return InvoiceLine::create(
            id: InvoiceLineId::fromString($id),
            label: 'Consultation standard',
            code: 'CONS_STD',
            quantity: '1',
            unitNetPrice: Money::fromMinorUnits($net, $eur),
            taxCategoryCode: 'veterinary.act.consultation',
            taxRatePercent: $rate,
            netAmount: Money::fromMinorUnits($net, $eur),
            taxAmount: Money::fromMinorUnits($tax, $eur),
            grossAmount: Money::fromMinorUnits($gross, $eur),
            source: InvoiceLineSource::CONSULTATION,
            sourceReference: 'line-1',
        );
    }

    private function payment(int $amount, string $id = '01960000-0000-7000-8000-0000000000d1'): Payment
    {
        return Payment::create(
            id: PaymentId::fromString($id),
            method: PaymentMethod::CARD,
            amount: Money::fromMinorUnits($amount, CurrencyCode::fromString('EUR')),
            receivedAt: $this->now(),
            reference: 'TX-1',
            recordedByUserId: '01960000-0000-7000-8000-0000000000e1',
        );
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-08-22 10:00:00');
    }
}
