<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Catalog\ListCatalog;

final readonly class PackageListRow
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public string $taxCategoryCode,
        public string $pricingMode,
        public ?int $fixedPriceMinorUnits,
        public string $currency,
        public int $componentCount,
        /** The weighted sum of the components' base prices, whatever the mode. */
        public int $componentsTotalMinorUnits,
        public string $status,
    ) {
    }

    public function isFixedPrice(): bool
    {
        return 'FIXED_PRICE' === $this->pricingMode;
    }

    /**
     * What the package is actually charged: its fixed price, or the sum of what
     * it holds.
     */
    public function effectivePriceMinorUnits(): int
    {
        return $this->isFixedPrice()
            ? ($this->fixedPriceMinorUnits ?? 0)
            : $this->componentsTotalMinorUnits;
    }

    public function isArchived(): bool
    {
        return 'ARCHIVED' === $this->status;
    }
}
