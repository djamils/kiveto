<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

/**
 * One chargeable line of a visit, as the Consultation BC records it.
 */
final readonly class BillableLineDto
{
    public function __construct(
        public string $consultationId,
        public string $sourceLineId,
        public string $label,
        public ?string $code,
        public string $quantity,
        public int $unitPriceMinorUnits,
        public string $currency,
        public ?string $taxCategoryCode,
    ) {
    }
}
