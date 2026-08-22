<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\CancelInvoice;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CancelInvoice implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $invoiceId,
        public string $reason,
    ) {
    }
}
