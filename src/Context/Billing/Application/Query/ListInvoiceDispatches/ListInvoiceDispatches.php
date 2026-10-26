<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\ListInvoiceDispatches;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The sends and reminders of an invoice with the delivery status of their
 * messages, for its history. Returns an InvoiceDispatchHistory.
 */
final readonly class ListInvoiceDispatches implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $invoiceId,
    ) {
    }
}
