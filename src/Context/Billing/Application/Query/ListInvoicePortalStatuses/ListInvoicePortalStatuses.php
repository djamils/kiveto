<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\ListInvoicePortalStatuses;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The live status and total of a batch of invoices of one clinic, for the
 * Client Portal's documents page (one call per page). Ids unknown to the
 * clinic are left out of the answer.
 */
final readonly class ListInvoicePortalStatuses implements QueryInterface
{
    /**
     * @param list<string> $invoiceIds
     */
    public function __construct(
        public string $clinicId,
        public array $invoiceIds,
    ) {
    }
}
