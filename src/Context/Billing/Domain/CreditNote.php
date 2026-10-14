<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain;

use App\Context\Billing\Domain\Event\CreditNoteIssued;
use App\Context\Billing\Domain\Exception\CurrencyMismatchException;
use App\Context\Billing\Domain\Exception\InvalidCreditNoteException;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\CreditNoteId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
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
 * Amounts are positive here: the document prints them as negative.
 */
final class CreditNote extends AggregateRoot
{
    private const int MAX_REASON_LENGTH = 255;

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
     * @param list<InvoiceLine> $lines
     */
    public static function issue(
        CreditNoteId $id,
        ClinicId $clinicId,
        InvoiceNumber $number,
        InvoiceId $invoiceId,
        string $invoiceNumber,
        Payer $payer,
        array $lines,
        string $reason,
        ?PaymentMethod $refundMethod,
        Money $refunded,
        CurrencyCode $currency,
        \DateTimeImmutable $now,
        ?string $issuedByUserId = null,
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
        ));

        return $creditNote;
    }

    /**
     * @param list<InvoiceLine> $lines
     */
    public static function reconstitute(
        CreditNoteId $id,
        ClinicId $clinicId,
        InvoiceNumber $number,
        InvoiceId $invoiceId,
        string $invoiceNumber,
        Payer $payer,
        array $lines,
        string $reason,
        ?PaymentMethod $refundMethod,
        Money $refunded,
        CurrencyCode $currency,
        \DateTimeImmutable $issuedAt,
        ?string $issuedByUserId,
    ): self {
        return new self(
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
    }

    public function totalNet(): Money
    {
        return $this->sum(static fn (InvoiceLine $line): int => $line->netAmount()->minorUnits());
    }

    public function totalTax(): Money
    {
        return $this->sum(static fn (InvoiceLine $line): int => $line->taxAmount()->minorUnits());
    }

    public function totalGross(): Money
    {
        return $this->sum(static fn (InvoiceLine $line): int => $line->grossAmount()->minorUnits());
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
