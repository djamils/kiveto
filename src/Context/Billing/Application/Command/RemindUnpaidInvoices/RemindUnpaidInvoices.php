<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\RemindUnpaidInvoices;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Relancer » one invoice, a selection, or every unpaid invoice of the
 * clinic: each eligible invoice is logged as reminded on its channels and
 * its reminder queued. Returns an InvoiceDispatchOutcome at once.
 */
final readonly class RemindUnpaidInvoices implements CommandInterface
{
    /**
     * @param list<string>|null $invoiceIds null: every unpaid invoice of the clinic
     */
    public function __construct(
        public string $clinicId,
        public ?array $invoiceIds,
        public ?string $actorUserId = null,
    ) {
    }
}
