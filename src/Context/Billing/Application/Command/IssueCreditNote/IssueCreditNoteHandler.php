<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\IssueCreditNote;

use App\Context\Billing\Domain\CreditNote;
use App\Context\Billing\Domain\Exception\InvalidCreditNoteException;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\Repository\CreditNoteRepositoryInterface;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\Service\CreditNoteCalculator;
use App\Context\Billing\Domain\Service\InvoiceNumberGeneratorInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\CreditNoteId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Issues a credit note for some lines of an invoice and applies it to the
 * invoice in the same transaction.
 *
 * A line is credited once. What each note credits per VAT rate is worked out
 * from the invoice's frozen amounts (CreditNoteCalculator): the note that
 * credits the last lines takes exactly what is left, so rounding never leaves
 * a cent hanging on a cancelled invoice.
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
        private CreditNoteCalculator $calculator = new CreditNoteCalculator(),
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
        $previous = $this->creditNotes->forInvoice($clinicId, $invoice->id());

        foreach ($previous as $creditNote) {
            $credited = [...$credited, ...$creditNote->creditedLineIds()];
        }

        $selected = $this->selectedLines($invoice, $command->lineIds, $credited);
        $credit   = $this->calculator->credit(
            $invoice,
            $selected,
            $previous,
            fn (): InvoiceLineId => InvoiceLineId::fromString($this->uuidGenerator->generate()),
        );
        $now   = $this->clock->now();
        $total = 0;

        foreach ($credit['breakdown'] as $bucket) {
            $total += $bucket->gross()->minorUnits();
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
            lines: $credit['lines'],
            breakdown: $credit['breakdown'],
            reason: $command->reason,
            refundMethod: $method,
            refunded: $refunded,
            currency: $invoice->currency(),
            now: $now,
            issuedByUserId: $command->issuedByUserId,
            processingRule: $invoice->processingRule(),
            framework: $invoice->framework(),
            invoiceIssuedAt: $invoice->issuedAt(),
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
}
