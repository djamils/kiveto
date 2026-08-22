<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\RecordInvoicePayment;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RecordInvoicePayment implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $invoiceId,
        public string $method,
        public int $amountMinorUnits,
        public ?string $reference = null,
        public ?string $recordedByUserId = null,
    ) {
    }
}
