<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\ListPortalPublishableInvoices;

final readonly class PortalPublishableInvoice
{
    public function __construct(
        public string $invoiceId,
        public string $clinicId,
    ) {
    }
}
