<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing\Service;

use App\Context\Catalog\Domain\Pricing\PriceRule;
use App\Context\Catalog\Domain\Pricing\ValueObject\AdjustmentType;
use App\Context\Catalog\Domain\Pricing\ValueObject\AppliedPriceRule;
use App\Shared\Money\Domain\RoundingPolicy\RoundingPolicy;
use App\Shared\Money\Domain\Service\MoneyCalculator;
use App\Shared\Money\Domain\ValueObject\Money;

final class PriceCalculator
{
    public function __construct(private MoneyCalculator $calc)
    {
    }

    /**
     * @param list<PriceRule> $sortedRules
     *
     * @return array{netAmount: Money, appliedRules: list<AppliedPriceRule>}
     */
    public function calculate(Money $basePrice, array $sortedRules, RoundingPolicy $rounding): array
    {
        $netAmount    = $basePrice;
        $appliedRules = [];

        foreach ($sortedRules as $rule) {
            $adjustment = $rule->adjustment();

            /** @var numeric-string $factor */
            $factor    = $adjustment->decimalValue();
            $netAmount = match ($adjustment->type()) {
                AdjustmentType::COEFFICIENT => $this->calc->multiply(
                    $netAmount,
                    $factor,
                    $rounding,
                ),
                AdjustmentType::FIXED_AMOUNT_DISCOUNT => $this->calc->subtract(
                    $netAmount,
                    $adjustment->money() ?? Money::fromMinorUnits(
                        (int) $adjustment->decimalValue(),
                        $netAmount->currency(),
                    ),
                ),
            };

            $appliedRules[] = new AppliedPriceRule(
                priceRuleId: $rule->id(),
                ruleType: $rule->ruleType(),
                adjustment: $adjustment,
            );
        }

        return [
            'netAmount'    => $netAmount,
            'appliedRules' => $appliedRules,
        ];
    }
}
