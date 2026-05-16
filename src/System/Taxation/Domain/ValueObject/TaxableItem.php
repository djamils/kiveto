<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\ValueObject;

use App\Shared\Money\Domain\ValueObject\Money;

final class TaxableItem
{
    private function __construct(
        private readonly Money $netAmount,
        private readonly TaxCategoryCode $category,
    ) {
    }

    public static function of(Money $netAmount, TaxCategoryCode $category): self
    {
        return new self($netAmount, $category);
    }

    public function netAmount(): Money
    {
        return $this->netAmount;
    }

    public function category(): TaxCategoryCode
    {
        return $this->category;
    }
}
