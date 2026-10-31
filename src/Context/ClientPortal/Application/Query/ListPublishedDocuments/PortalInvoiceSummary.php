<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListPublishedDocuments;

final readonly class PortalInvoiceSummary
{
    /**
     * @param string $paymentStatus unpaid|partially_paid|paid|credited
     */
    public function __construct(
        public int $totalMinorUnits,
        public string $currency,
        public string $paymentStatus,
    ) {
    }
}
