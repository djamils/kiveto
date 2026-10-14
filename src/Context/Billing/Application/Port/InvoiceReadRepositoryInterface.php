<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

use App\Context\Billing\Application\Query\FindInvoiceForAdmission\AdmissionInvoiceRow;
use App\Context\Billing\Application\Query\GetBillingStats\BillingStats;
use App\Context\Billing\Application\Query\GetCreditNoteDetails\CreditNoteDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Billing\Application\Query\SearchInvoices\InvoiceListRow;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoicesCriteria;
use App\Context\Billing\Domain\ValueObject\AdmissionId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\CreditNoteId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;

interface InvoiceReadRepositoryInterface
{
    /**
     * @throws \DomainException when the invoice is unknown to this clinic
     */
    public function details(ClinicId $clinicId, InvoiceId $invoiceId): InvoiceDetails;

    /**
     * One page of the clinic's invoices — and, when asked, credit notes, in
     * one register — plus the unpaginated total.
     *
     * @return array{items: list<InvoiceListRow>, total: int}
     */
    public function search(ClinicId $clinicId, SearchInvoicesCriteria $criteria): array;

    /**
     * Headline figures for the list page: what was collected over the period,
     * and what is still owed whatever the period.
     */
    public function stats(
        ClinicId $clinicId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        ?\DateTimeImmutable $overdueIssuedBefore = null,
    ): BillingStats;

    /**
     * @throws \DomainException when the credit note is unknown to this clinic
     */
    public function creditNoteDetails(ClinicId $clinicId, CreditNoteId $creditNoteId): CreditNoteDetails;

    /**
     * What each client still owes the clinic, in minor units, across their
     * issued and partly paid invoices. Clients who owe nothing are left out.
     *
     * @return array<string, int> client id => balance due
     */
    public function outstandingByPayer(ClinicId $clinicId): array;

    /**
     * The invoice raised for one visit, or null while the counter has not been
     * through it.
     */
    public function forAdmission(ClinicId $clinicId, AdmissionId $admissionId): ?AdmissionInvoiceRow;
}
