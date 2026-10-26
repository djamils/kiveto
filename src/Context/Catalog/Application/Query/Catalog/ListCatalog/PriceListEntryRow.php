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
        /** The item's own base price mode, null when the item is gone. */
        public ?string $basePriceMode,
        public int $priceMinorUnits,
        /** 'incl' or 'excl': the mode this list's price was entered in. */
        public string $priceMode,
        public string $currency,
        public ?string $taxCategoryOverride,
        public ?string $itemTaxCategoryCode,
    ) {
    }

    /**
     * Null when the item behind the override has been deleted outright — the
     * override is then dangling and worth showing as such — or when the two
     * prices were entered in different modes: Catalog never converts one into
     * the other, so they cannot be compared here.
     */
    public function differenceMinorUnits(): ?int
    {
        return null !== $this->basePriceMinorUnits && $this->basePriceMode === $this->priceMode
            ? $this->priceMinorUnits - $this->basePriceMinorUnits
            : null;
    }

    public function isDangling(): bool
    {
        return null === $this->itemName;
    }
}
