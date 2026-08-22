<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

use App\Context\Billing\Application\Query\FindInvoiceForAdmission\AdmissionInvoiceRow;
use App\Context\Billing\Application\Query\GetBillingStats\BillingStats;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Billing\Application\Query\SearchInvoices\InvoiceListRow;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoicesCriteria;
use App\Context\Billing\Domain\ValueObject\AdmissionId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;

interface InvoiceReadRepositoryInterface
{
    /**
     * @throws \DomainException when the invoice is unknown to this clinic
     */
    public function details(ClinicId $clinicId, InvoiceId $invoiceId): InvoiceDetails;

    /**
     * One page of the clinic's invoices, plus the unpaginated total.
     *
     * @return array{items: list<InvoiceListRow>, total: int}
     */
    public function search(ClinicId $clinicId, SearchInvoicesCriteria $criteria): array;

    /**
     * Headline figures for the list page: what was collected over the period,
     * and what is still owed whatever the period.
     */
    public function stats(ClinicId $clinicId, \DateTimeImmutable $from, \DateTimeImmutable $to): BillingStats;

    /**
     * The invoice raised for one visit, or null while the counter has not been
     * through it.
     */
    public function forAdmission(ClinicId $clinicId, AdmissionId $admissionId): ?AdmissionInvoiceRow;
}
