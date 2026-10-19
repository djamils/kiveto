<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain;

use App\Context\Billing\Domain\Event\CreditNoteIssued;
use App\Context\Billing\Domain\Exception\CurrencyMismatchException;
use App\Context\Billing\Domain\Exception\InvalidCreditNoteException;
use App\Context\Billing\Domain\ValueObject\CalculatedAmounts;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\CreditNoteId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use App\Context\Billing\Domain\ValueObject\InvoiceTypeCode;
use App\Context\Billing\Domain\ValueObject\InvoicingFramework;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Context\Billing\Domain\ValueObject\ProcessingRule;
use App\Context\Billing\Domain\ValueObject\RateBucket;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;

/**
 * « Avoir »: the document that corrects an issued invoice.
 *
 * An issued invoice is never edited. What was billed wrongly, returned or
 * waived is cancelled out by a credit note, which takes its own number in its
 * own continuous series and lists the lines it cancels. It is born issued and
 * never changes afterwards; what it gave back to the client (if they had
 * already paid) is part of it.
 *
 * Amounts are positive here: the document prints them as negative. They are
 * worked out once, when the note is issued, from the frozen amounts of the
 * invoice it credits, and never recalculated.
 */
final class CreditNote extends AggregateRoot
{
    private const int MAX_REASON_LENGTH = 255;

    private CalculatedAmounts $amounts;

    private ProcessingRule $processingRule = ProcessingRule::B2C;

    private ?InvoicingFramework $framework = null;

    private ?\DateTimeImmutable $invoiceIssuedAt = null;

    /**
     * @param list<InvoiceLine> $lines
     */
    private function __construct(
        private readonly CreditNoteId $id,
        private readonly ClinicId $clinicId,
        private readonly InvoiceNumber $number,
        private readonly InvoiceId $invoiceId,
        private readonly string $invoiceNumber,
        private readonly Payer $payer,
        private readonly array $lines,
        private readonly string $reason,
        private readonly ?PaymentMethod $refundMethod,
        private readonly Money $refunded,
        private readonly CurrencyCode $currency,
        private readonly \DateTimeImmutable $issuedAt,
        private readonly ?string $issuedByUserId,
    ) {
    }

    /**
     * @param list<InvoiceLine> $lines     the credited lines, with their amounts
     * @param list<RateBucket>  $breakdown what the note credits per VAT rate
     */
    public static function issue(
        CreditNoteId $id,
        ClinicId $clinicId,
        InvoiceNumber $number,
        InvoiceId $invoiceId,
        string $invoiceNumber,
        Payer $payer,
        array $lines,
        array $breakdown,
        string $reason,
        ?PaymentMethod $refundMethod,
        Money $refunded,
        CurrencyCode $currency,
        \DateTimeImmutable $now,
        ?string $issuedByUserId = null,
        ProcessingRule $processingRule = ProcessingRule::B2C,
        ?InvoicingFramework $framework = null,
        ?\DateTimeImmutable $invoiceIssuedAt = null,
    ): self {
        $reason = mb_trim($reason);

        if ('' === $reason) {
            throw new InvalidCreditNoteException('A credit note needs a reason.');
        }

        if ([] === $lines) {
            throw new InvalidCreditNoteException('A credit note needs at least one line.');
        }

        foreach ($lines as $line) {
            if (!$line->currency()->equals($currency)) {
                throw new CurrencyMismatchException($currency->toString(), $line->currency()->toString());
            }
        }

        if (!$refunded->currency()->equals($currency)) {
            throw new CurrencyMismatchException($currency->toString(), $refunded->currency()->toString());
        }

        $creditNote = new self(
            id: $id,
            clinicId: $clinicId,
            number: $number,
            invoiceId: $invoiceId,
            invoiceNumber: $invoiceNumber,
            payer: $payer,
            lines: $lines,
            reason: mb_substr($reason, 0, self::MAX_REASON_LENGTH),
            refundMethod: $refunded->isPositive() ? $refundMethod : null,
            refunded: $refunded,
            currency: $currency,
            issuedAt: $now,
            issuedByUserId: $issuedByUserId,
        );

        $creditNote->amounts         = $creditNote->amountsOf($lines, $breakdown);
        $creditNote->processingRule  = $processingRule;
        $creditNote->framework       = $framework;
        $creditNote->invoiceIssuedAt = $invoiceIssuedAt;

        if ($refunded->minorUnits() < 0 || $refunded->minorUnits() > $creditNote->totalGross()->minorUnits()) {
            throw new InvalidCreditNoteException('A credit note cannot give back more than it credits.');
        }

        if ($refunded->isPositive() && null === $refundMethod) {
            throw new InvalidCreditNoteException('A refund needs a payment method.');
        }

        $creditNote->recordDomainEvent(new CreditNoteIssued(
            creditNoteId: $id->toString(),
            clinicId: $clinicId->toString(),
            invoiceId: $invoiceId->toString(),
            number: $number->toString(),
            totalGrossMinorUnits: $creditNote->totalGross()->minorUnits(),
            refundedMinorUnits: $refunded->minorUnits(),
            currency: $currency->toString(),
            issuedAt: $now->format(\DateTimeInterface::ATOM),
            processingRule: $processingRule->value,
        ));

        return $creditNote;
    }

    /**
     * @param list<InvoiceLine> $lines
     * @param list<RateBucket>  $breakdown
     */
    public static function reconstitute(
        CreditNoteId $id,
        ClinicId $clinicId,
        InvoiceNumber $number,
        InvoiceId $invoiceId,
        string $invoiceNumber,
        Payer $payer,
        array $lines,
        array $breakdown,
        string $reason,
        ?PaymentMethod $refundMethod,
        Money $refunded,
        CurrencyCode $currency,
        \DateTimeImmutable $issuedAt,
        ?string $issuedByUserId,
        ProcessingRule $processingRule = ProcessingRule::B2C,
        ?InvoicingFramework $framework = null,
        ?\DateTimeImmutable $invoiceIssuedAt = null,
    ): self {
        $creditNote = new self(
            $id,
            $clinicId,
            $number,
            $invoiceId,
            $invoiceNumber,
            $payer,
            $lines,
            $reason,
            $refundMethod,
            $refunded,
            $currency,
            $issuedAt,
            $issuedByUserId,
        );

        $creditNote->amounts = CalculatedAmounts::of(
            array_map(static fn (InvoiceLine $line) => $line->amounts(), $lines),
            $breakdown,
            $currency,
        );
        $creditNote->processingRule  = $processingRule;
        $creditNote->framework       = $framework;
        $creditNote->invoiceIssuedAt = $invoiceIssuedAt;

        return $creditNote;
    }

    public function typeCode(): InvoiceTypeCode
    {
        return InvoiceTypeCode::CREDIT_NOTE;
    }

    /** The processing rule of the invoice the note credits. */
    public function processingRule(): ProcessingRule
    {
        return $this->processingRule;
    }

    /** The invoicing framework of the invoice the note credits. */
    public function framework(): ?InvoicingFramework
    {
        return $this->framework;
    }

    /** When the credited invoice was issued (BT-26). */
    public function invoiceIssuedAt(): ?\DateTimeImmutable
    {
        return $this->invoiceIssuedAt;
    }

    public function amounts(): CalculatedAmounts
    {
        return $this->amounts;
    }

    /** Σ credited line nets (BT-106). */
    public function totalNetBeforeDiscount(): Money
    {
        return $this->amounts->totalLineNet();
    }

    /** The share of the invoice's discount the note gives back (BT-107). */
    public function discountAmount(): Money
    {
        return $this->amounts->totalAllowance();
    }

    /** The credited total excl. VAT (BT-109). */
    public function totalNet(): Money
    {
        return $this->amounts->taxBasis();
    }

    public function totalTax(): Money
    {
        return $this->amounts->totalVat();
    }

    public function totalGross(): Money
    {
        return $this->amounts->totalGross();
    }

    /**
     * @return list<array{ratePercent: string, netMinorUnits: int, taxMinorUnits: int, grossMinorUnits: int, allowanceMinorUnits: int}>
     */
    public function taxBreakdown(): array
    {
        return array_map(
            static fn (RateBucket $bucket): array => [
                'ratePercent'         => $bucket->ratePercent(),
                'netMinorUnits'       => $bucket->base()->minorUnits(),
                'taxMinorUnits'       => $bucket->vat()->minorUnits(),
                'grossMinorUnits'     => $bucket->gross()->minorUnits(),
                'allowanceMinorUnits' => $bucket->allowance()->minorUnits(),
            ],
            $this->amounts->buckets(),
        );
    }

    /**
     * The invoice lines this credit note cancels out.
     *
     * @return list<string>
     */
    public function creditedLineIds(): array
    {
        $ids = [];

        foreach ($this->lines as $line) {
            if (null !== $line->sourceReference()) {
                $ids[] = $line->sourceReference();
            }
        }

        return $ids;
    }

    public function id(): CreditNoteId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function number(): InvoiceNumber
    {
        return $this->number;
    }

    public function invoiceId(): InvoiceId
    {
        return $this->invoiceId;
    }

    public function invoiceNumber(): string
    {
        return $this->invoiceNumber;
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

    public function reason(): string
    {
        return $this->reason;
    }

    public function refundMethod(): ?PaymentMethod
    {
        return $this->refundMethod;
    }

    public function refunded(): Money
    {
        return $this->refunded;
    }

    public function currency(): CurrencyCode
    {
        return $this->currency;
    }

    public function issuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function issuedByUserId(): ?string
    {
        return $this->issuedByUserId;
    }

    /**
     * The breakdown must account for exactly the credited lines.
     *
     * @param list<InvoiceLine> $lines
     * @param list<RateBucket>  $breakdown
     */
    private function amountsOf(array $lines, array $breakdown): CalculatedAmounts
    {
        $amounts = CalculatedAmounts::of(
            array_map(static fn (InvoiceLine $line) => $line->amounts(), $lines),
            $breakdown,
            $this->currency,
        );

        $lineNets = 0;
        foreach ($lines as $line) {
            $lineNets += $line->netAmount()->minorUnits();
        }

        if ($lineNets !== $amounts->totalLineNet()->minorUnits()) {
            throw new InvalidCreditNoteException('The credited amounts per VAT rate do not match the credited lines.');
        }

        return $amounts;
    }
}
