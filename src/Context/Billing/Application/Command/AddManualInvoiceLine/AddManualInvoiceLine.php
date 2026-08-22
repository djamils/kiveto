<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\AddManualInvoiceLine;

use App\Shared\Application\Bus\CommandInterface;

final readonly class AddManualInvoiceLine implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $invoiceId,
        public string $label,
        public string $quantity,
        public int $unitPriceMinorUnits,
        public ?string $taxCategoryCode = null,
    ) {
    }
}
