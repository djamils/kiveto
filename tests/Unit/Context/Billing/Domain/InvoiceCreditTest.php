<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Domain;

use App\Context\Billing\Domain\Event\InvoiceCredited;
use App\Context\Billing\Domain\Exception\CreditExceedsInvoiceException;
use App\Context\Billing\Domain\Exception\CurrencyMismatchException;
use App\Context\Billing\Domain\Exception\InvoiceCannotBeCancelledException;
use App\Context\Billing\Domain\Exception\InvoiceNotCreditableException;
use App\Context\Billing\Domain\Exception\PaymentExceedsBalanceException;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use App\Context\Billing\Domain\ValueObject\InvoiceStatus;
use App\Context\Billing\Domain\ValueObject\OperationCategory;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\Payment;
use App\Context\Billing\Domain\ValueObject\PaymentId;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Context\Billing\Domain\ValueObject\PriceMode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * An issued invoice is corrected by credit notes: what they cancel out comes
 * off what is owed, and what the client paid beyond what is left is either
 * given back or kept as a credit.
 */
final class InvoiceCreditTest extends TestCase
{
    private int $payments = 10;

    public function testCreditingPartOfAnUnpaidInvoiceLowersWhatIsOwed(): void
    {
        $invoice = $this->issued();

        $refund = $invoice->credit($this->eur(2000), true, $this->now());

        self::assertSame(0, $refund->minorUnits());
        self::assertSame(2000, $invoice->amountCredited()->minorUnits());
        self::assertSame(4000, $invoice->creditableAmount()->minorUnits());
        self::assertSame(4000, $invoice->balanceDue()->minorUnits());
        self::assertSame(InvoiceStatus::ISSUED, $invoice->status());

        $events = $invoice->recordedDomainEvents();
        $last   = $events[\count($events) - 1];
        self::assertInstanceOf(InvoiceCredited::class, $last);
        self::assertSame(2000, $last->creditedMinorUnits);
    }

    public function testCreditingAllOfItFilesTheInvoiceAsCancelledByCreditNote(): void
    {
        $invoice = $this->issued();

        $invoice->credit($this->eur(6000), false, $this->now());

        self::assertSame(InvoiceStatus::CREDITED, $invoice->status());
        self::assertSame(0, $invoice->balanceDue()->minorUnits());
        self::assertSame('Annulée par avoir', $invoice->status()->label());
    }

    public function testWhatAPaidClientOverpaidIsRefundedWhenAsked(): void
    {
        $invoice = $this->issued();
        $invoice->recordPayment($this->payment(6000), $this->now());

        $refund = $invoice->credit($this->eur(1500), true, $this->now());

        self::assertSame(1500, $refund->minorUnits());
        self::assertSame(1500, $invoice->amountRefunded()->minorUnits());
        self::assertSame(0, $invoice->balanceDue()->minorUnits());
        self::assertSame(InvoiceStatus::PAID, $invoice->status());
    }

    public function testWhatIsKeptAsACreditLeavesTheBalanceNegative(): void
    {
        $invoice = $this->issued();
        $invoice->recordPayment($this->payment(6000), $this->now());

        $refund = $invoice->credit($this->eur(1500), false, $this->now());

        self::assertSame(0, $refund->minorUnits());
        self::assertSame(-1500, $invoice->balanceDue()->minorUnits());
        self::assertSame(InvoiceStatus::PAID, $invoice->status());
    }

    public function testCreditingWhatWasLeftToPaySettlesAPartlyPaidInvoice(): void
    {
        $invoice = $this->issued();
        $invoice->recordPayment($this->payment(4000), $this->now());

        $invoice->credit($this->eur(2000), true, $this->now());

        self::assertSame(InvoiceStatus::PAID, $invoice->status());
        self::assertNotNull($invoice->paidAt());
        self::assertSame(0, $invoice->amountRefunded()->minorUnits());
    }

    public function testAPaymentCannotExceedWhatIsLeftAfterACredit(): void
    {
        $invoice = $this->issued();
        $invoice->credit($this->eur(5000), false, $this->now());

        $this->expectException(PaymentExceedsBalanceException::class);

        $invoice->recordPayment($this->payment(2000), $this->now());
    }

    public function testACreditCannotExceedWhatIsLeftToCredit(): void
    {
        $invoice = $this->issued();
        $invoice->credit($this->eur(5000), false, $this->now());

        $this->expectException(CreditExceedsInvoiceException::class);

        $invoice->credit($this->eur(1001), false, $this->now());
    }

    public function testACreditMustBePositive(): void
    {
        $this->expectException(CreditExceedsInvoiceException::class);

        $this->issued()->credit($this->eur(0), false, $this->now());
    }

    public function testACreditIsInTheInvoicesCurrency(): void
    {
        $this->expectException(CurrencyMismatchException::class);

        $this->issued()->credit(Money::fromMinorUnits(100, CurrencyCode::fromString('USD')), false, $this->now());
    }

    public function testADraftCannotBeCredited(): void
    {
        $this->expectException(InvoiceNotCreditableException::class);
        $this->expectExceptionMessage('while it is DRAFT');

        $this->draft()->credit($this->eur(100), false, $this->now());
    }

    public function testACreditedInvoiceCannotBeCancelled(): void
    {
        $invoice = $this->issued();
        $invoice->credit($this->eur(100), false, $this->now());

        $this->expectException(InvoiceCannotBeCancelledException::class);
        $this->expectExceptionMessage('a credit note was already issued against it');

        $invoice->cancel('Erreur', $this->now());
    }

    public function testOnlyIssuedInvoicesAreCreditable(): void
    {
        self::assertTrue(InvoiceStatus::ISSUED->isCreditable());
        self::assertTrue(InvoiceStatus::PARTIALLY_PAID->isCreditable());
        self::assertTrue(InvoiceStatus::PAID->isCreditable());
        self::assertFalse(InvoiceStatus::DRAFT->isCreditable());
        self::assertFalse(InvoiceStatus::CANCELLED->isCreditable());
        self::assertFalse(InvoiceStatus::CREDITED->isCreditable());
        self::assertFalse(InvoiceStatus::CREDITED->isOutstanding());
        self::assertFalse(InvoiceStatus::CREDITED->isEditable());
    }

    public function testReconstitutedCreditsComeBack(): void
    {
        $issued = $this->issued();

        $invoice = Invoice::reconstitute(
            $issued->id(),
            $issued->clinicId(),
            $issued->number(),
            InvoiceStatus::PAID,
            null,
            $issued->payer(),
            $issued->lines(),
            [],
            null,
            $issued->currency(),
            null,
            $this->now(),
            $this->now(),
            $this->now(),
            $this->now(),
            null,
            null,
            1000,
            400,
            null,
            $issued->amounts()->buckets(),
        );

        self::assertSame(1000, $invoice->amountCredited()->minorUnits());
        self::assertSame(400, $invoice->amountRefunded()->minorUnits());
        self::assertSame(5400, $invoice->balanceDue()->minorUnits());
    }

    private function draft(): Invoice
    {
        $invoice = Invoice::draft(
            InvoiceId::fromString('01960000-0000-7000-8000-00000000d001'),
            ClinicId::fromString('01960000-0000-7000-8000-00000000d002'),
            null,
            Payer::anonymous(),
            CurrencyCode::fromString('EUR'),
            $this->now(),
        );

        $invoice->addLine(InvoiceLine::create(
            id: InvoiceLineId::fromString('01960000-0000-7000-8000-00000000d003'),
            label: 'Consultation standard',
            code: 'CONS_STD',
            quantity: '1',
            enteredUnitPrice: $this->eur(6000),
            priceMode: PriceMode::INCLUDING_TAX,
            taxCategoryCode: 'veterinary.act.consultation',
            taxRatePercent: '20',
            operationCategory: OperationCategory::SERVICES,
            source: InvoiceLineSource::MANUAL,
        ), $this->now());

        return $invoice;
    }

    private function issued(): Invoice
    {
        $invoice = $this->draft();
        $invoice->issue(InvoiceNumber::of(2026, 1), $this->now());

        return $invoice;
    }

    private function payment(int $amount): Payment
    {
        return Payment::create(
            PaymentId::fromString('01960000-0000-7000-8000-00000000d0' . ++$this->payments),
            PaymentMethod::CARD,
            $this->eur($amount),
            $this->now(),
        );
    }

    private function eur(int $minorUnits): Money
    {
        return Money::fromMinorUnits($minorUnits, CurrencyCode::fromString('EUR'));
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-14 10:00:00');
    }
}
