<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\IssueCreditNote;

use App\Context\Billing\Domain\CreditNote;
use App\Context\Billing\Domain\Exception\InvalidCreditNoteException;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\Repository\CreditNoteRepositoryInterface;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\Service\InvoiceNumberGeneratorInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\CreditNoteId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Issues a credit note for some lines of an invoice and applies it to the
 * invoice in the same transaction.
 *
 * A line is credited once. The amounts credited are what the client was
 * actually charged for the line — after the invoice's discount — and the
 * credit note that empties the invoice credits exactly what is left of it, so
 * rounding never leaves a cent hanging on a cancelled invoice.
 */
#[AsMessageHandler]
final readonly class IssueCreditNoteHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private CreditNoteRepositoryInterface $creditNotes,
        private InvoiceNumberGeneratorInterface $numbers,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(IssueCreditNote $command): string
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $invoice  = $this->invoices->get($clinicId, InvoiceId::fromString($command->invoiceId));
        $number   = $invoice->number();

        if (null === $number) {
            throw new InvalidCreditNoteException('Only an issued invoice can be credited.');
        }

        $credited = [];

        foreach ($this->creditNotes->forInvoice($clinicId, $invoice->id()) as $creditNote) {
            $credited = [...$credited, ...$creditNote->creditedLineIds()];
        }

        $selected = $this->selectedLines($invoice, $command->lineIds, $credited);
        $lines    = $this->creditLines($invoice, $selected, \count($selected) + \count($credited) >= \count($invoice->lines()));
        $now      = $this->clock->now();
        $total    = 0;

        foreach ($lines as $line) {
            $total += $line->grossAmount()->minorUnits();
        }

        $method   = null !== $command->refundMethod ? PaymentMethod::from($command->refundMethod) : null;
        $refunded = $invoice->credit(Money::fromMinorUnits($total, $invoice->currency()), null !== $method, $now);

        $creditNote = CreditNote::issue(
            id: CreditNoteId::fromString($this->uuidGenerator->generate()),
            clinicId: $clinicId,
            number: $this->numbers->nextCreditNote($clinicId, (int) $now->format('Y')),
            invoiceId: $invoice->id(),
            invoiceNumber: $number->toString(),
            payer: $invoice->payer(),
            lines: $lines,
            reason: $command->reason,
            refundMethod: $method,
            refunded: $refunded,
            currency: $invoice->currency(),
            now: $now,
            issuedByUserId: $command->issuedByUserId,
        );

        $this->invoices->save($invoice);
        $this->creditNotes->save($creditNote);

        return $creditNote->id()->toString();
    }

    /**
     * @param list<string> $lineIds
     * @param list<string> $credited
     *
     * @return list<InvoiceLine>
     */
    private function selectedLines(Invoice $invoice, array $lineIds, array $credited): array
    {
        if ([] === $lineIds) {
            throw new InvalidCreditNoteException('Choose at least one line to credit.');
        }

        $selected = [];

        foreach ($invoice->lines() as $line) {
            if (\in_array($line->id()->toString(), $lineIds, true)) {
                if (\in_array($line->id()->toString(), $credited, true)) {
                    throw new InvalidCreditNoteException(\sprintf('Line %s was already credited.', $line->id()->toString()));
                }

                $selected[] = $line;
            }
        }

        if (\count($selected) !== \count(array_unique($lineIds))) {
            throw new InvalidCreditNoteException('A line to credit does not belong to the invoice.');
        }

        return $selected;
    }

    /**
     * The selected lines as charged: their net and VAT scaled down by the
     * invoice's discount, if it had one.
     *
     * @param list<InvoiceLine> $selected
     *
     * @return list<InvoiceLine>
     */
    private function creditLines(Invoice $invoice, array $selected, bool $closesInvoice): array
    {
        $before = $invoice->totalNetBeforeDiscount()->minorUnits();
        $after  = $invoice->totalNet()->minorUnits();
        $scale  = static fn (int $amount): int => 0 === $before ? $amount : (int) round($amount * $after / $before);
        $nets   = [];
        $taxes  = [];

        foreach ($selected as $i => $line) {
            $nets[$i]  = $scale($line->netAmount()->minorUnits());
            $taxes[$i] = $scale($line->taxAmount()->minorUnits());
        }

        $sum        = array_sum($nets) + array_sum($taxes);
        $creditable = $invoice->creditableAmount()->minorUnits();
        $last       = \count($selected) - 1;

        // The last credit note takes exactly what is left; no note takes more.
        if ($closesInvoice || $sum > $creditable) {
            $nets[$last] += $creditable - $sum;
        }

        $currency = $invoice->currency();
        $lines    = [];

        foreach ($selected as $i => $line) {
            $lines[] = InvoiceLine::create(
                id: InvoiceLineId::fromString($this->uuidGenerator->generate()),
                label: $line->label(),
                code: $line->code(),
                quantity: $line->quantity(),
                unitNetPrice: Money::fromMinorUnits($scale($line->unitNetPrice()->minorUnits()), $currency),
                taxCategoryCode: $line->taxCategoryCode(),
                taxRatePercent: $line->taxRatePercent(),
                netAmount: Money::fromMinorUnits($nets[$i], $currency),
                taxAmount: Money::fromMinorUnits($taxes[$i], $currency),
                grossAmount: Money::fromMinorUnits($nets[$i] + $taxes[$i], $currency),
                source: InvoiceLineSource::CREDIT,
                sourceReference: $line->id()->toString(),
            );
        }

        return $lines;
    }
}
