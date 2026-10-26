<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Catalog\ListCatalog;

final readonly class ActListRow
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public ?string $description,
        public string $category,
        public string $taxCategoryCode,
        public int $basePriceMinorUnits,
        public string $currency,
        /** 'incl' (all taxes included) or 'excl': the mode the price was entered in. */
        public string $basePriceMode,
        public int $estimatedDurationMinutes,
        public bool $requiresAnesthesia,
        public string $status,
    ) {
    }

    public function isArchived(): bool
    {
        return 'ARCHIVED' === $this->status;
    }
}
