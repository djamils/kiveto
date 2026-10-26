<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing;

use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListItemId;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemRef;
use App\Context\Catalog\Domain\ValueObject\CatalogPrice;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;

final class PriceListItem
{
    private function __construct(
        private PriceListItemId $id,
        private CatalogItemRef $itemRef,
        private CatalogPrice $price,
        private ?TaxCategoryCode $taxCategoryOverride,
    ) {
        if ($price->minorUnits() < 0) {
            throw new \DomainException('PriceListItem price cannot be negative.');
        }
    }

    public static function create(
        PriceListItemId $id,
        CatalogItemRef $itemRef,
        CatalogPrice $price,
        ?TaxCategoryCode $taxCategoryOverride,
    ): self {
        return new self(
            id: $id,
            itemRef: $itemRef,
            price: $price,
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

    public function price(): CatalogPrice
    {
        return $this->price;
    }

    public function taxCategoryOverride(): ?TaxCategoryCode
    {
        return $this->taxCategoryOverride;
    }

    public function updatePrice(CatalogPrice $price, ?TaxCategoryCode $taxCategoryOverride): void
    {
        if ($price->minorUnits() < 0) {
            throw new \DomainException('PriceListItem price cannot be negative.');
        }

        $this->price               = $price;
        $this->taxCategoryOverride = $taxCategoryOverride;
    }
}
