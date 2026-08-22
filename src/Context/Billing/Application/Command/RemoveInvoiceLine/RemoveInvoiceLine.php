<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\RemoveInvoiceLine;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RemoveInvoiceLine implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $invoiceId,
        public string $lineId,
    ) {
    }
}
