<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Act\GetActDetail;

final readonly class ActDetailView
{
    public function __construct(
        public string $id,
        public string $clinicId,
        public string $name,
        public string $code,
        public ?string $description,
        public string $category,
        public string $taxCategoryCode,
        public int $basePriceMinorUnits,
        public string $basePriceCurrency,
        /** 'incl' (all taxes included) or 'excl': the mode the price was entered in. */
        public string $basePriceMode,
        public int $estimatedDurationMinutes,
        public bool $requiresAnesthesia,
        public string $status,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
