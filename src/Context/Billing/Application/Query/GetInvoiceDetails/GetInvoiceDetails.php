<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\GetInvoiceDetails;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetInvoiceDetails implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $invoiceId,
    ) {
    }
}
