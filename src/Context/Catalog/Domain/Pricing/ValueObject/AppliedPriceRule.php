<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing\ValueObject;

final readonly class AppliedPriceRule
{
    public function __construct(
        public PriceRuleId $priceRuleId,
        public PriceRuleType $ruleType,
        public PriceAdjustment $adjustment,
    ) {
    }
}
