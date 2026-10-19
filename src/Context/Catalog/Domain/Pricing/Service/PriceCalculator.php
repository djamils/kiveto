<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing\Service;

use App\Context\Catalog\Domain\Pricing\PriceRule;
use App\Context\Catalog\Domain\Pricing\ValueObject\AdjustmentType;
use App\Context\Catalog\Domain\Pricing\ValueObject\AppliedPriceRule;
use App\Context\Catalog\Domain\ValueObject\CatalogPrice;
use App\Shared\Money\Domain\RoundingPolicy\RoundingPolicy;
use App\Shared\Money\Domain\Service\MoneyCalculator;
use App\Shared\Money\Domain\ValueObject\Money;

/**
 * Applies price rules to a catalogue price, in the mode the price was
 * entered in: a coefficient is mode-neutral, and a fixed amount is read in
 * the same mode as the price it adjusts. No tax is ever computed here.
 */
final class PriceCalculator
{
    public function __construct(private MoneyCalculator $calc)
    {
    }

    /**
     * @param list<PriceRule> $sortedRules
     *
     * @return array{price: CatalogPrice, appliedRules: list<AppliedPriceRule>}
     */
    public function calculate(CatalogPrice $basePrice, array $sortedRules, RoundingPolicy $rounding): array
    {
        $amount       = $basePrice->amount();
        $appliedRules = [];

        foreach ($sortedRules as $rule) {
            $adjustment = $rule->adjustment();

            /** @var numeric-string $factor */
            $factor = $adjustment->decimalValue();
            $amount = match ($adjustment->type()) {
                AdjustmentType::COEFFICIENT => $this->calc->multiply(
                    $amount,
                    $factor,
                    $rounding,
                ),
                AdjustmentType::FIXED_AMOUNT_DISCOUNT => $this->calc->subtract(
                    $amount,
                    $adjustment->money() ?? Money::fromMinorUnits(
                        (int) $adjustment->decimalValue(),
                        $amount->currency(),
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
            'price'        => $basePrice->withAmount($amount),
            'appliedRules' => $appliedRules,
        ];
    }
}
