<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\AddManualInvoiceLine;

use App\Shared\Application\Bus\CommandInterface;

/**
 * A line typed by hand on a draft: its price is typed VAT included, and its
 * Taxation category — mandatory — gives its rate and its nature.
 */
final readonly class AddManualInvoiceLine implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $invoiceId,
        public string $label,
        public string $quantity,
        public int $unitPriceMinorUnits,
        public string $taxCategoryCode,
    ) {
    }
}
