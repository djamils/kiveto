<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain;

use App\Context\Billing\Domain\Event\InvoiceCancelled;
use App\Context\Billing\Domain\Event\InvoiceCredited;
use App\Context\Billing\Domain\Event\InvoiceDrafted;
use App\Context\Billing\Domain\Event\InvoiceIssued;
use App\Context\Billing\Domain\Event\InvoicePaid;
use App\Context\Billing\Domain\Event\PaymentRecorded;
use App\Context\Billing\Domain\Exception\CreditExceedsInvoiceException;
use App\Context\Billing\Domain\Exception\CurrencyMismatchException;
use App\Context\Billing\Domain\Exception\InvoiceCannotBeCancelledException;
use App\Context\Billing\Domain\Exception\InvoiceHasNoLinesException;
use App\Context\Billing\Domain\Exception\InvoiceNotCreditableException;
use App\Context\Billing\Domain\Exception\InvoiceNotEditableException;
use App\Context\Billing\Domain\Exception\InvoiceNotPayableException;
use App\Context\Billing\Domain\Exception\InvoiceRequiresIdentifiedPayerException;
use App\Context\Billing\Domain\Exception\PaymentExceedsBalanceException;
use App\Context\Billing\Domain\ValueObject\AdmissionId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\Discount;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use App\Context\Billing\Domain\ValueObject\InvoiceStatus;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\Payment;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;

/**
 * What a client owes the clinic for one visit, and what they have paid.
 *
 * The aggregate is deliberately two-phased. While it is a draft it is a working
 * document: lines come and go, a discount can be tried out, nothing is
 * committed. Issuing freezes it — it takes its number, its amounts stop moving,
 * and from then on the only things that can change are how much of it is paid
 * and how much of it credit notes have cancelled out (an issued invoice is
 * never edited: it is corrected by a credit note, which carries its own number).
 */
final class Invoice extends AggregateRoot
{
    /**
     * @param list<InvoiceLine> $lines
     * @param list<Payment>     $payments
     */
    private function __construct(
        private readonly InvoiceId $id,
        private readonly ClinicId $clinicId,
        private ?InvoiceNumber $number,
        private InvoiceStatus $status,
        private readonly ?AdmissionId $admissionId,
        private Payer $payer,
        private array $lines,
        private array $payments,
        private ?Discount $discount,
        private readonly CurrencyCode $currency,
        private ?string $notes,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
        private ?\DateTimeImmutable $issuedAt,
        private ?\DateTimeImmutable $paidAt,
        private ?\DateTimeImmutable $cancelledAt,
        private ?string $cancellationReason,
        private int $creditedMinorUnits = 0,
        private int $refundedMinorUnits = 0,
    ) {
    }

    public static function draft(
        InvoiceId $id,
        ClinicId $clinicId,
        ?AdmissionId $admissionId,
        Payer $payer,
        CurrencyCode $currency,
        \DateTimeImmutable $now,
    ): self {
        $invoice = new self(
            id: $id,
            clinicId: $clinicId,
            number: null,
            status: InvoiceStatus::DRAFT,
            admissionId: $admissionId,
            payer: $payer,
            lines: [],
            payments: [],
            discount: null,
            currency: $currency,
            notes: null,
            createdAt: $now,
            updatedAt: $now,
            issuedAt: null,
            paidAt: null,
            cancelledAt: null,
            cancellationReason: null,
        );

        $invoice->recordDomainEvent(new InvoiceDrafted(
            invoiceId: $id->toString(),
            clinicId: $clinicId->toString(),
            admissionId: $admissionId?->toString(),
            draftedAt: $now->format(\DateTimeInterface::ATOM),
        ));

        return $invoice;
    }

    /**
     * @param list<InvoiceLine> $lines
     * @param list<Payment>     $payments
     */
    public static function reconstitute(
        InvoiceId $id,
        ClinicId $clinicId,
        ?InvoiceNumber $number,
        InvoiceStatus $status,
        ?AdmissionId $admissionId,
        Payer $payer,
        array $lines,
        array $payments,
        ?Discount $discount,
        CurrencyCode $currency,
        ?string $notes,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        ?\DateTimeImmutable $issuedAt,
        ?\DateTimeImmutable $paidAt,
        ?\DateTimeImmutable $cancelledAt,
        ?string $cancellationReason,
        int $creditedMinorUnits = 0,
        int $refundedMinorUnits = 0,
    ): self {
        return new self(
            $id,
            $clinicId,
            $number,
            $status,
            $admissionId,
            $payer,
            $lines,
            $payments,
            $discount,
            $currency,
            $notes,
            $createdAt,
            $updatedAt,
            $issuedAt,
            $paidAt,
            $cancelledAt,
            $cancellationReason,
            $creditedMinorUnits,
            $refundedMinorUnits,
        );
    }

    public function addLine(InvoiceLine $line, \DateTimeImmutable $now): void
    {
        $this->ensureEditable();

        if (!$line->currency()->equals($this->currency)) {
            throw new CurrencyMismatchException($this->currency->toString(), $line->currency()->toString());
        }

        $this->lines[]   = $line;
        $this->updatedAt = $now;
    }

    public function removeLine(InvoiceLineId $lineId, \DateTimeImmutable $now): void
    {
        $this->ensureEditable();

        $this->lines = array_values(array_filter(
            $this->lines,
            static fn (InvoiceLine $line): bool => !$line->id()->equals($lineId),
        ));

        $this->updatedAt = $now;
    }

    public function applyDiscount(?Discount $discount, \DateTimeImmutable $now): void
    {
        $this->ensureEditable();

        $this->discount  = $discount;
        $this->updatedAt = $now;
    }

    public function changePayer(Payer $payer, \DateTimeImmutable $now): void
    {
        $this->ensureEditable();

        $this->payer     = $payer;
        $this->updatedAt = $now;
    }

    public function setNotes(?string $notes, \DateTimeImmutable $now): void
    {
        $this->ensureEditable();

        $notes           = null !== $notes ? mb_trim($notes) : null;
        $this->notes     = '' === $notes ? null : $notes;
        $this->updatedAt = $now;
    }

    public function issue(InvoiceNumber $number, \DateTimeImmutable $now): void
    {
        $this->ensureEditable();

        if ([] === $this->lines) {
            throw new InvoiceHasNoLinesException($this->id->toString());
        }

        // The invoice of a visit is addressed to the animal's owner: nobody
        // cashes a patient whose identity is still unknown.
        if (null !== $this->admissionId && null === $this->payer->clientId()) {
            throw new InvoiceRequiresIdentifiedPayerException($this->id->toString());
        }

        $this->number    = $number;
        $this->status    = InvoiceStatus::ISSUED;
        $this->issuedAt  = $now;
        $this->updatedAt = $now;

        $this->recordDomainEvent(new InvoiceIssued(
            invoiceId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            number: $number->toString(),
            totalGrossMinorUnits: $this->totalGross()->minorUnits(),
            currency: $this->currency->toString(),
            issuedAt: $now->format(\DateTimeInterface::ATOM),
        ));

        // A fully discounted or zero-amount invoice is settled the moment it is
        // issued: there is nothing left to collect.
        if (!$this->balanceDue()->isPositive()) {
            $this->markPaid($now);
        }
    }

    public function recordPayment(Payment $payment, \DateTimeImmutable $now): void
    {
        if (!$this->status->isOutstanding()) {
            throw new InvoiceNotPayableException($this->id->toString(), $this->status);
        }

        if (!$payment->amount()->currency()->equals($this->currency)) {
            throw new CurrencyMismatchException(
                $this->currency->toString(),
                $payment->amount()->currency()->toString(),
            );
        }

        $balance = $this->balanceDue();

        if ($payment->amount()->minorUnits() > $balance->minorUnits()) {
            throw new PaymentExceedsBalanceException($payment->amount()->minorUnits(), $balance->minorUnits());
        }

        $this->payments[] = $payment;
        $this->updatedAt  = $now;

        $this->recordDomainEvent(new PaymentRecorded(
            invoiceId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            paymentId: $payment->id()->toString(),
            method: $payment->method()->value,
            amountMinorUnits: $payment->amount()->minorUnits(),
            currency: $this->currency->toString(),
            receivedAt: $payment->receivedAt()->format(\DateTimeInterface::ATOM),
        ));

        if ($this->balanceDue()->isPositive()) {
            $this->status = InvoiceStatus::PARTIALLY_PAID;

            return;
        }

        $this->markPaid($now);
    }

    public function cancel(string $reason, \DateTimeImmutable $now): void
    {
        if (InvoiceStatus::CANCELLED === $this->status) {
            throw new InvoiceCannotBeCancelledException($this->id->toString(), 'it is already cancelled');
        }

        if ([] !== $this->payments) {
            throw new InvoiceCannotBeCancelledException(
                $this->id->toString(),
                'a payment was already recorded against it',
            );
        }

        if ($this->creditedMinorUnits > 0) {
            throw new InvoiceCannotBeCancelledException(
                $this->id->toString(),
                'a credit note was already issued against it',
            );
        }

        $reason = mb_trim($reason);

        if ('' === $reason) {
            throw new InvoiceCannotBeCancelledException($this->id->toString(), 'a reason is required');
        }

        $this->status             = InvoiceStatus::CANCELLED;
        $this->cancellationReason = $reason;
        $this->cancelledAt        = $now;
        $this->updatedAt          = $now;

        $this->recordDomainEvent(new InvoiceCancelled(
            invoiceId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            reason: $reason,
            cancelledAt: $now->format(\DateTimeInterface::ATOM),
        ));
    }

    /**
     * Cancels out part or all of the invoice — the effect of a credit note.
     *
     * What the client already paid beyond what is left to pay is either given
     * back (refunded, by the credit note's own means) or kept as a credit in
     * the client's favour. Crediting the whole invoice files it as « annulée
     * par avoir »; crediting the part that was still owed settles it.
     *
     * @return Money the amount handed back to the client
     */
    public function credit(Money $amount, bool $refundOverpayment, \DateTimeImmutable $now): Money
    {
        if (!$this->status->isCreditable()) {
            throw new InvoiceNotCreditableException($this->id->toString(), $this->status);
        }

        if (!$amount->currency()->equals($this->currency)) {
            throw new CurrencyMismatchException($this->currency->toString(), $amount->currency()->toString());
        }

        $creditable = $this->creditableAmount()->minorUnits();

        if (!$amount->isPositive() || $amount->minorUnits() > $creditable) {
            throw new CreditExceedsInvoiceException($amount->minorUnits(), $creditable);
        }

        $this->creditedMinorUnits += $amount->minorUnits();

        $overpaid = -$this->balanceDue()->minorUnits();
        $refund   = $refundOverpayment && $overpaid > 0 ? $overpaid : 0;

        $this->refundedMinorUnits += $refund;
        $this->updatedAt = $now;

        $this->recordDomainEvent(new InvoiceCredited(
            invoiceId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            creditedMinorUnits: $amount->minorUnits(),
            refundedMinorUnits: $refund,
            currency: $this->currency->toString(),
            creditedAt: $now->format(\DateTimeInterface::ATOM),
        ));

        if (!$this->creditableAmount()->isPositive()) {
            $this->status = InvoiceStatus::CREDITED;
        } elseif (!$this->balanceDue()->isPositive() && InvoiceStatus::PAID !== $this->status) {
            $this->markPaid($now);
        }

        return Money::fromMinorUnits($refund, $this->currency);
    }

    /**
     * What credit notes may still cancel out.
     */
    public function creditableAmount(): Money
    {
        return Money::fromMinorUnits(
            $this->totalGross()->minorUnits() - $this->creditedMinorUnits,
            $this->currency,
        );
    }

    public function amountCredited(): Money
    {
        return Money::fromMinorUnits($this->creditedMinorUnits, $this->currency);
    }

    public function amountRefunded(): Money
    {
        return Money::fromMinorUnits($this->refundedMinorUnits, $this->currency);
    }

    public function totalNetBeforeDiscount(): Money
    {
        return $this->sum(static fn (InvoiceLine $line): int => $line->netAmount()->minorUnits());
    }

    public function discountAmount(): Money
    {
        if (null === $this->discount) {
            return Money::zero($this->currency);
        }

        return Money::fromMinorUnits(
            $this->discount->amountOff($this->totalNetBeforeDiscount()->minorUnits()),
            $this->currency,
        );
    }

    public function totalNet(): Money
    {
        return Money::fromMinorUnits(
            $this->totalNetBeforeDiscount()->minorUnits() - $this->discountAmount()->minorUnits(),
            $this->currency,
        );
    }

    /**
     * The discount reduces the taxable base, so the VAT of every line is scaled
     * by the same ratio rather than left untouched.
     */
    public function totalTax(): Money
    {
        $rawTax = $this->sum(static fn (InvoiceLine $line): int => $line->taxAmount()->minorUnits());
        $rawNet = $this->totalNetBeforeDiscount()->minorUnits();
        $off    = $this->discountAmount()->minorUnits();

        if (0 === $off || 0 === $rawNet) {
            return $rawTax;
        }

        $scaled = (int) round($rawTax->minorUnits() * ($rawNet - $off) / $rawNet);

        return Money::fromMinorUnits($scaled, $this->currency);
    }

    public function totalGross(): Money
    {
        return Money::fromMinorUnits(
            $this->totalNet()->minorUnits() + $this->totalTax()->minorUnits(),
            $this->currency,
        );
    }

    public function amountPaid(): Money
    {
        $total = 0;

        foreach ($this->payments as $payment) {
            $total += $payment->amount()->minorUnits();
        }

        return Money::fromMinorUnits($total, $this->currency);
    }

    /**
     * What the client still owes: the invoice, less what credit notes cancelled
     * out, less what they paid and kept. Negative when they paid more than is
     * left and kept the difference as a credit.
     */
    public function balanceDue(): Money
    {
        return Money::fromMinorUnits(
            $this->totalGross()->minorUnits() - $this->creditedMinorUnits
                - $this->amountPaid()->minorUnits() + $this->refundedMinorUnits,
            $this->currency,
        );
    }

    /**
     * Net and tax totals per VAT rate, highest rate first — the order an
     * invoice legally prints them in.
     *
     * A list rather than a map keyed by rate: PHP would silently turn "20" into
     * the integer 20 and sort it against "5.5" as a string.
     *
     * @return list<array{ratePercent: string, netMinorUnits: int, taxMinorUnits: int}>
     */
    public function taxBreakdown(): array
    {
        $byRate = [];

        foreach ($this->lines as $line) {
            $key = $line->taxRatePercent();

            $byRate[$key] ??= ['ratePercent' => $key, 'netMinorUnits' => 0, 'taxMinorUnits' => 0];

            $byRate[$key]['netMinorUnits'] += $line->netAmount()->minorUnits();
            $byRate[$key]['taxMinorUnits'] += $line->taxAmount()->minorUnits();
        }

        $breakdown = array_values($byRate);

        usort(
            $breakdown,
            // The rates were validated as decimals when the lines were built.
            static function (array $a, array $b): int {
                /** @var numeric-string $left */
                $left = $b['ratePercent'];
                /** @var numeric-string $right */
                $right = $a['ratePercent'];

                return bccomp($left, $right, 4);
            },
        );

        return $breakdown;
    }

    public function id(): InvoiceId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function number(): ?InvoiceNumber
    {
        return $this->number;
    }

    public function status(): InvoiceStatus
    {
        return $this->status;
    }

    public function admissionId(): ?AdmissionId
    {
        return $this->admissionId;
    }

    public function payer(): Payer
    {
        return $this->payer;
    }

    /**
     * @return list<InvoiceLine>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * @return list<Payment>
     */
    public function payments(): array
    {
        return $this->payments;
    }

    public function discount(): ?Discount
    {
        return $this->discount;
    }

    public function currency(): CurrencyCode
    {
        return $this->currency;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function issuedAt(): ?\DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function paidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function cancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function cancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    private function markPaid(\DateTimeImmutable $now): void
    {
        $this->status = InvoiceStatus::PAID;
        $this->paidAt = $now;

        $this->recordDomainEvent(new InvoicePaid(
            invoiceId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            admissionId: $this->admissionId?->toString(),
            totalGrossMinorUnits: $this->totalGross()->minorUnits(),
            currency: $this->currency->toString(),
            paidAt: $now->format(\DateTimeInterface::ATOM),
        ));
    }

    private function ensureEditable(): void
    {
        if (!$this->status->isEditable()) {
            throw new InvoiceNotEditableException($this->id->toString(), $this->status);
        }
    }

    /**
     * @param callable(InvoiceLine): int $extract
     */
    private function sum(callable $extract): Money
    {
        $total = 0;

        foreach ($this->lines as $line) {
            $total += $extract($line);
        }

        return Money::fromMinorUnits($total, $this->currency);
    }
}
