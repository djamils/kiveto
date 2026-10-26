<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Dispatch;

use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;

interface InvoiceDispatchRepositoryInterface
{
    /** Inserts the dispatch, or records its notification ids. */
    public function save(InvoiceDispatch $dispatch): void;

    public function find(InvoiceDispatchId $id): ?InvoiceDispatch;

    /**
     * The dispatches of an invoice, oldest first.
     *
     * @return list<InvoiceDispatch>
     */
    public function listForInvoice(ClinicId $clinicId, InvoiceId $invoiceId): array;

    public function countReminders(ClinicId $clinicId, InvoiceId $invoiceId): int;

    public function lastReminderAt(ClinicId $clinicId, InvoiceId $invoiceId): ?\DateTimeImmutable;

    /**
     * The invoices of the clinic reminded at or after this moment.
     *
     * @return list<string> invoice ids
     */
    public function remindedSince(ClinicId $clinicId, \DateTimeImmutable $since): array;
}
