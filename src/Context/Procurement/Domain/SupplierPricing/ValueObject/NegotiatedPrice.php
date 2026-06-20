<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierPricing\ValueObject;

use App\Shared\Money\Domain\ValueObject\Money;

final readonly class NegotiatedPrice
{
    private function __construct(
        public readonly Money $amount,
        public readonly ?string $discountPercentage,
        public readonly ?string $notes,
    ) {
    }

    public static function create(Money $amount, ?string $discountPercentage = null, ?string $notes = null): self
    {
        if ($amount->minorUnits() <= 0) {
            throw new \InvalidArgumentException('NegotiatedPrice amount must be greater than zero.');
        }

        return new self($amount, $discountPercentage, $notes);
    }
}
