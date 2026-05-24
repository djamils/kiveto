<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing;

use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListItemId;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemRef;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;

final class PriceListItem
{
    private function __construct(
        private PriceListItemId $id,
        private CatalogItemRef $itemRef,
        private Money $netPrice,
        private ?TaxCategoryCode $taxCategoryOverride,
    ) {
        if ($netPrice->minorUnits() < 0) {
            throw new \DomainException('PriceListItem netPrice cannot be negative.');
        }
    }

    public static function create(
        PriceListItemId $id,
        CatalogItemRef $itemRef,
        Money $netPrice,
        ?TaxCategoryCode $taxCategoryOverride,
    ): self {
        return new self(
            id: $id,
            itemRef: $itemRef,
            netPrice: $netPrice,
            taxCategoryOverride: $taxCategoryOverride,
        );
    }

    public function id(): PriceListItemId
    {
        return $this->id;
    }

    public function itemRef(): CatalogItemRef
    {
        return $this->itemRef;
    }

    public function netPrice(): Money
    {
        return $this->netPrice;
    }

    public function taxCategoryOverride(): ?TaxCategoryCode
    {
        return $this->taxCategoryOverride;
    }

    public function updatePrice(Money $netPrice, ?TaxCategoryCode $taxCategoryOverride): void
    {
        if ($netPrice->minorUnits() < 0) {
            throw new \DomainException('PriceListItem netPrice cannot be negative.');
        }

        $this->netPrice            = $netPrice;
        $this->taxCategoryOverride = $taxCategoryOverride;
    }
}
