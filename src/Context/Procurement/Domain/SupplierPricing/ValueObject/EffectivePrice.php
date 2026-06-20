<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierPricing\ValueObject;

use App\Shared\Money\Domain\ValueObject\Money;

final readonly class EffectivePrice
{
    public function __construct(
        public readonly Money $amount,
        public readonly SupplierPricingSource $source,
        public readonly \DateTimeImmutable $resolvedAt,
    ) {
    }
}
