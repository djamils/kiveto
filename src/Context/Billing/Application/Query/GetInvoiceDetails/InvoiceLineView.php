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
        /** The unit price as entered, VAT included or not according to unitPriceMode. */
        public int $unitPriceMinorUnits,
        public string $unitPriceMode,
        /** The unit net price at six decimals (BT-146). */
        public string $unitNet,
        public int $netMinorUnits,
        public int $grossMinorUnits,
        public string $taxRatePercent,
        public ?string $taxCategoryCode,
        public string $source,
        /** The consultation line it was billed from, the article sold, or the invoice line a credit note cancels. */
        public ?string $sourceReference = null,
    ) {
    }
}
