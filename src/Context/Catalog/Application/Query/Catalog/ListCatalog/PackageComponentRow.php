<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Catalog\ListCatalog;

/**
 * One line of a package, resolved against the act or the article it holds.
 */
final readonly class PackageComponentRow
{
    public function __construct(
        public string $id,
        public string $itemType,
        public string $itemId,
        public ?string $itemCode,
        public ?string $itemName,
        public ?int $unitPriceMinorUnits,
        /** The component's base price mode ('incl' or 'excl'), null when the item is gone. */
        public ?string $unitPriceMode,
        public string $currency,
        public int $quantity,
        public int $weight,
    ) {
    }

    public function lineTotalMinorUnits(): int
    {
        return ($this->unitPriceMinorUnits ?? 0) * $this->quantity;
    }

    public function isDangling(): bool
    {
        return null === $this->itemName;
    }
}
