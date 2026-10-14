<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Repository;

use App\Context\Billing\Domain\CreditNote;
use App\Context\Billing\Domain\Exception\CreditNoteNotFoundException;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\CreditNoteId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;

interface CreditNoteRepositoryInterface
{
    public function save(CreditNote $creditNote): void;

    /**
     * @throws CreditNoteNotFoundException
     */
    public function get(ClinicId $clinicId, CreditNoteId $creditNoteId): CreditNote;

    /**
     * The credit notes issued against one invoice, oldest first.
     *
     * @return list<CreditNote>
     */
    public function forInvoice(ClinicId $clinicId, InvoiceId $invoiceId): array;
}
