<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Support;

use App\Context\Billing\Application\Port\InvoiceReadRepositoryInterface;
use App\Context\Billing\Application\Port\OutstandingInvoiceRow;
use App\Context\Billing\Application\Query\FindInvoiceForAdmission\AdmissionInvoiceRow;
use App\Context\Billing\Application\Query\GetBillingStats\BillingStats;
use App\Context\Billing\Application\Query\GetCreditNoteDetails\CreditNoteDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoicesCriteria;
use App\Context\Billing\Domain\ValueObject\AdmissionId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\CreditNoteId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;

/**
 * The invoices of one clinic (Invoices::CLINIC); only what the dispatch use
 * cases read.
 */
final class FakeInvoiceReads implements InvoiceReadRepositoryInterface
{
    /** @var array<string, InvoiceDetails> */
    public array $invoices = [];

    public function __construct(InvoiceDetails ...$invoices)
    {
        foreach ($invoices as $invoice) {
            $this->invoices[$invoice->id] = $invoice;
        }
    }

    public function details(ClinicId $clinicId, InvoiceId $invoiceId): InvoiceDetails
    {
        $invoice = $this->invoices[$invoiceId->toString()] ?? null;

        if (null === $invoice || $invoice->clinicId !== $clinicId->toString()) {
            throw new \DomainException('Unknown invoice.');
        }

        return $invoice;
    }

    public function search(ClinicId $clinicId, SearchInvoicesCriteria $criteria): array
    {
        throw new \LogicException('Not used.');
    }

    public function stats(ClinicId $clinicId, \DateTimeImmutable $from, \DateTimeImmutable $to, ?\DateTimeImmutable $overdueIssuedBefore = null): BillingStats
    {
        throw new \LogicException('Not used.');
    }

    public function creditNoteDetails(ClinicId $clinicId, CreditNoteId $creditNoteId): CreditNoteDetails
    {
        throw new \LogicException('Not used.');
    }

    public function outstandingByPayer(ClinicId $clinicId): array
    {
        throw new \LogicException('Not used.');
    }

    public function forAdmission(ClinicId $clinicId, AdmissionId $admissionId): ?AdmissionInvoiceRow
    {
        throw new \LogicException('Not used.');
    }

    public function outstandingInvoices(ClinicId $clinicId, ?array $only = null): array
    {
        $ids  = null !== $only ? array_map(static fn (InvoiceId $id): string => $id->toString(), $only) : null;
        $rows = [];

        foreach ($this->invoices as $invoice) {
            $outstanding = \in_array($invoice->status, ['ISSUED', 'PARTIALLY_PAID'], true) && null !== $invoice->number;

            if ($outstanding && $invoice->clinicId === $clinicId->toString() && (null === $ids || \in_array($invoice->id, $ids, true))) {
                $rows[] = new OutstandingInvoiceRow($invoice->id, (string) $invoice->number, $invoice->payerClientId, $invoice->issuedAt ?? $invoice->createdAt, $invoice->balanceDueMinorUnits);
            }
        }

        return $rows;
    }
}
