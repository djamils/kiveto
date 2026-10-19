<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing\ValueObject;

use App\Context\Catalog\Domain\ValueObject\CatalogPrice;
use App\Context\Catalog\Domain\ValueObject\PriceEntryMode;
use App\Shared\Money\Domain\ValueObject\Money;

/**
 * The price Catalog resolved for an item, in the mode it was entered in.
 *
 * `netAmount` and `baseAmount` keep their historical names but are amounts
 * in `mode()`: tax included when the clinic entered the price TTC. Catalog
 * never converts them; the VAT rate is resolved at billing time.
 */
final readonly class ResolvedPrice
{
    /** @param list<AppliedPriceRule> $appliedRules */
    public function __construct(
        public Money $netAmount,
        public Money $baseAmount,
        public array $appliedRules,
        public PriceListId $sourcePriceList,
        public ?PriceListItemId $sourcePriceListItem,
        public \DateTimeImmutable $resolvedAt,
        private PriceEntryMode $mode,
    ) {
    }

    /** The resolved amount, after the price rules, in mode(). */
    public function amount(): Money
    {
        return $this->netAmount;
    }

    public function mode(): PriceEntryMode
    {
        return $this->mode;
    }

    public function price(): CatalogPrice
    {
        return CatalogPrice::of($this->netAmount, $this->mode);
    }
}
