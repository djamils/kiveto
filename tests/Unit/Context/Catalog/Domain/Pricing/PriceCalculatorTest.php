<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Domain\Pricing;

use App\Context\Catalog\Domain\Pricing\PriceRule;
use App\Context\Catalog\Domain\Pricing\Service\PriceCalculator;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceAdjustment;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceRuleCondition;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceRuleId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceRuleType;
use App\Context\Catalog\Domain\ValueObject\CatalogPrice;
use App\Context\Catalog\Domain\ValueObject\PriceEntryMode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\Service\CurrencyRegistry;
use App\Shared\Money\Domain\Service\MoneyCalculator;
use App\Shared\Money\Domain\Service\RoundingPolicyRegistry;
use App\Shared\Money\Domain\ValueObject\Currency;
use App\Shared\Money\Domain\ValueObject\CurrencyDecimals;
use App\Shared\Money\Domain\ValueObject\CurrencySymbol;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class PriceCalculatorTest extends TestCase
{
    public function testRulesApplyInTheModeThePriceWasEnteredIn(): void
    {
        foreach ([PriceEntryMode::INCLUDING_TAX, PriceEntryMode::EXCLUDING_TAX] as $mode) {
            $result = $this->calculator()->calculate(
                CatalogPrice::of($this->eur(1480), $mode),
                [
                    $this->rule('01960000-0000-7000-8000-00000000f001', PriceAdjustment::coefficient('1.5')),
                    // A fixed amount is read in the same mode as the price it adjusts.
                    $this->rule('01960000-0000-7000-8000-00000000f002', PriceAdjustment::fixedAmountDiscount($this->eur(200))),
                ],
                (new RoundingPolicyRegistry())->commercial(),
            );

            // 1480 × 1.5 = 2220, then − 200: no tax is computed on the way.
            self::assertSame(2020, $result['price']->minorUnits());
            self::assertSame($mode, $result['price']->mode());
            self::assertCount(2, $result['appliedRules']);
        }
    }

    public function testWithoutRulesThePriceComesBackAsEntered(): void
    {
        $price  = CatalogPrice::excludingTax($this->eur(4500));
        $result = $this->calculator()->calculate($price, [], (new RoundingPolicyRegistry())->commercial());

        self::assertTrue($price->equals($result['price']));
        self::assertSame([], $result['appliedRules']);
    }

    private function rule(string $id, PriceAdjustment $adjustment): PriceRule
    {
        return PriceRule::create(
            id: PriceRuleId::fromString($id),
            ruleType: PriceRuleType::DISCOUNT,
            condition: PriceRuleCondition::create(true, [], null, null, null, null),
            adjustment: $adjustment,
        );
    }

    private function eur(int $minorUnits): Money
    {
        return Money::fromMinorUnits($minorUnits, CurrencyCode::fromString('EUR'));
    }

    private function calculator(): PriceCalculator
    {
        $currencies = $this->createStub(CurrencyRegistry::class);
        $currencies->method('get')->willReturn(Currency::of(
            code: CurrencyCode::fromString('EUR'),
            symbol: CurrencySymbol::fromString('€'),
            decimals: CurrencyDecimals::of(2),
            displayName: 'Euro',
        ));

        return new PriceCalculator(new MoneyCalculator($currencies));
    }
}
