<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\ApplyInvoiceDiscount;

use App\Shared\Application\Bus\CommandInterface;

/**
 * A null kind clears the discount.
 */
final readonly class ApplyInvoiceDiscount implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $invoiceId,
        public ?string $kind = null,
        public ?string $value = null,
        public ?string $reason = null,
    ) {
    }
}
