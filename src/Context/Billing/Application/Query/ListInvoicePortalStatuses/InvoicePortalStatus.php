<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\ListInvoicePortalStatuses;

final readonly class InvoicePortalStatus
{
    public function __construct(
        public string $invoiceId,
        // The InvoiceStatus value as stored (e.g. 'ISSUED', 'PAID').
        public string $status,
        public int $totalGrossMinorUnits,
        public string $currency,
    ) {
    }
}
