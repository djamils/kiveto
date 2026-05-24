<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing;

use App\Context\Catalog\Domain\Pricing\ValueObject\PriceAdjustment;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceRuleCondition;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceRuleId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceRuleType;

final class PriceRule
{
    private function __construct(
        private PriceRuleId $id,
        private PriceRuleType $ruleType,
        private PriceRuleCondition $condition,
        private PriceAdjustment $adjustment,
    ) {
    }

    public static function create(
        PriceRuleId $id,
        PriceRuleType $ruleType,
        PriceRuleCondition $condition,
        PriceAdjustment $adjustment,
    ): self {
        return new self(
            id: $id,
            ruleType: $ruleType,
            condition: $condition,
            adjustment: $adjustment,
        );
    }

    public function id(): PriceRuleId
    {
        return $this->id;
    }

    public function ruleType(): PriceRuleType
    {
        return $this->ruleType;
    }

    public function condition(): PriceRuleCondition
    {
        return $this->condition;
    }

    public function adjustment(): PriceAdjustment
    {
        return $this->adjustment;
    }
}
