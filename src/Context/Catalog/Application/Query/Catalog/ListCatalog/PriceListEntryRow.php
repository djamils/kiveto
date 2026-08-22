<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Catalog\ListCatalog;

/**
 * One override of a price list, resolved against the item it applies to: what
 * the item normally costs, and what this list charges instead.
 */
final readonly class PriceListEntryRow
{
    public function __construct(
        public string $id,
        public string $itemType,
        public string $itemId,
        public ?string $itemCode,
        public ?string $itemName,
        public ?int $basePriceMinorUnits,
        public int $netPriceMinorUnits,
        public string $currency,
        public ?string $taxCategoryOverride,
        public ?string $itemTaxCategoryCode,
    ) {
    }

    /**
     * Null when the item behind the override has been deleted outright — the
     * override is then dangling and worth showing as such.
     */
    public function differenceMinorUnits(): ?int
    {
        return null !== $this->basePriceMinorUnits
            ? $this->netPriceMinorUnits - $this->basePriceMinorUnits
            : null;
    }

    public function isDangling(): bool
    {
        return null === $this->itemName;
    }
}
