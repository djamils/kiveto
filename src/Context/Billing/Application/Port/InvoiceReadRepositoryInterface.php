<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

use App\Context\Billing\Application\Query\FindInvoiceForAdmission\AdmissionInvoiceRow;
use App\Context\Billing\Application\Query\GetBillingStats\BillingStats;
use App\Context\Billing\Application\Query\GetCreditNoteDetails\CreditNoteDetails;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Billing\Application\Query\ListInvoicePortalStatuses\InvoicePortalStatus;
use App\Context\Billing\Application\Query\ListPortalPublishableInvoices\PortalPublishableInvoice;
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
     * What was collected over the period, in minor units: the `stats` figure
     * alone, in one statement.
     */
    public function collectedBetween(ClinicId $clinicId, \DateTimeImmutable $from, \DateTimeImmutable $to): int;

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

    /**
     * The clinic's issued and partly paid invoices (credit notes never are),
     * oldest first, with what is left to pay — all of them, or only those of
     * these ids.
     *
     * @param list<InvoiceId>|null $only
     *
     * @return list<OutstandingInvoiceRow>
     */
    public function outstandingInvoices(ClinicId $clinicId, ?array $only = null): array;

    /**
     * The live status and total of these invoices, in one read; ids that do
     * not belong to the clinic are left out.
     *
     * @param list<InvoiceId> $invoiceIds
     *
     * @return list<InvoicePortalStatus>
     */
    public function portalStatuses(ClinicId $clinicId, array $invoiceIds): array;

    /**
     * Across all clinics: invoices issued since the date (issued, paid or
     * credited — never draft or cancelled) whose payer is a client, ordered
     * by id, after the given one, at most $limit.
     *
     * @return list<PortalPublishableInvoice>
     */
    public function portalPublishable(\DateTimeImmutable $issuedFrom, ?InvoiceId $afterId, int $limit): array;
}
