<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\ListBillableLinesForAdmission;

/**
 * One chargeable line of a visit, as recorded on a consultation.
 */
final readonly class BillableLine
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
