<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Package;

use App\Context\Catalog\Domain\Package\ValueObject\PackageComponentId;
use App\Context\Catalog\Domain\Package\ValueObject\Quantity;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemRef;

final class PackageComponent
{
    private function __construct(
        private PackageComponentId $id,
        private CatalogItemRef $itemRef,
        private Quantity $quantity,
        private int $weight,
    ) {
    }

    public static function create(
        PackageComponentId $id,
        CatalogItemRef $itemRef,
        Quantity $quantity,
        int $weight,
    ): self {
        return new self(
            id: $id,
            itemRef: $itemRef,
            quantity: $quantity,
            weight: $weight,
        );
    }

    public function id(): PackageComponentId
    {
        return $this->id;
    }

    public function itemRef(): CatalogItemRef
    {
        return $this->itemRef;
    }

    public function quantity(): Quantity
    {
        return $this->quantity;
    }

    public function weight(): int
    {
        return $this->weight;
    }
}
