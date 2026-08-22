<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Repository;

use App\Context\Billing\Domain\Exception\InvoiceNotFoundException;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\ValueObject\AdmissionId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;

interface InvoiceRepositoryInterface
{
    public function save(Invoice $invoice): void;

    /**
     * @throws InvoiceNotFoundException
     */
    public function get(ClinicId $clinicId, InvoiceId $invoiceId): Invoice;

    public function findById(ClinicId $clinicId, InvoiceId $invoiceId): ?Invoice;

    /**
     * The invoice a visit is being charged on, whatever its status.
     *
     * A visit is charged once; finding the existing one is what keeps a second
     * visit to the checkout screen from opening a second draft.
     */
    public function findForAdmission(ClinicId $clinicId, AdmissionId $admissionId): ?Invoice;
}
