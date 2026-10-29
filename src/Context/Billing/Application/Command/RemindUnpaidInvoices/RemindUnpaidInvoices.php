<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\RemindUnpaidInvoices;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Relancer » one invoice or a selection — « Relancer tous les impayés »
 * posts exactly the ids its confirmation showed: each eligible invoice is
 * logged as reminded on its channels and its reminder queued. Returns an
 * InvoiceDispatchOutcome at once.
 */
final readonly class RemindUnpaidInvoices implements CommandInterface
{
    /** One request reminds at most this many invoices. */
    public const int MAX_INVOICES = 500;

    /**
     * @param list<string> $invoiceIds
     */
    public function __construct(
        public string $clinicId,
        public array $invoiceIds,
        public ?string $actorUserId = null,
    ) {
        if (\count($invoiceIds) > self::MAX_INVOICES) {
            throw new \InvalidArgumentException(\sprintf('At most %d invoices are reminded at once.', self::MAX_INVOICES));
        }
    }
}
