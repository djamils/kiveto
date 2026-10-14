<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\GetInvoiceDetails;

final readonly class InvoiceLineView
{
    public function __construct(
        public string $id,
        public string $label,
        public ?string $code,
        public string $quantity,
        public int $unitPriceMinorUnits,
        public int $netMinorUnits,
        public int $taxMinorUnits,
        public int $grossMinorUnits,
        public string $taxRatePercent,
        public ?string $taxCategoryCode,
        public string $source,
        /** The consultation line it was billed from, the article sold, or the invoice line a credit note cancels. */
        public ?string $sourceReference = null,
    ) {
    }
}
