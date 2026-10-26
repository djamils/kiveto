<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Domain\Service;

use App\Context\Billing\Domain\Service\InvoiceAmountCalculator;
use App\Context\Billing\Domain\ValueObject\CalculatedAmounts;
use App\Context\Billing\Domain\ValueObject\CalculationLine;
use App\Context\Billing\Domain\ValueObject\CalculationMode;
use App\Context\Billing\Domain\ValueObject\Discount;
use App\Context\Billing\Domain\ValueObject\PriceMode;
use App\Context\Billing\Domain\ValueObject\RateBucket;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\CurrencyDecimals;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InvoiceAmountCalculatorTest extends TestCase
{
    public function testThreeTimesTenEurosIncludingTaxFirst(): void
    {
        $amounts = $this->calculate(CalculationMode::INCLUDING_TAX_FIRST, [
            $this->line(1000, PriceMode::INCLUDING_TAX, '1', '20'),
            $this->line(1000, PriceMode::INCLUDING_TAX, '1', '20'),
            $this->line(1000, PriceMode::INCLUDING_TAX, '1', '20'),
        ]);

        self::assertSame(3000, $amounts->totalGross()->minorUnits());
        self::assertSame(2500, $amounts->taxBasis()->minorUnits());
        self::assertSame(500, $amounts->totalVat()->minorUnits());
        self::assertSame([834, 833, 833], $this->nets($amounts));
        self::assertSame([1000, 1000, 1000], $this->grosses($amounts));
        self::assertSame('8.333333', $amounts->lines()[0]->unitNet()->toDecimalString());
        $this->assertBrCo10And13($amounts);
    }

    public function testThreeTimesTenEurosExcludingTaxFirst(): void
    {
        $amounts = $this->calculate(CalculationMode::EXCLUDING_TAX_FIRST, [
            $this->line(1000, PriceMode::INCLUDING_TAX, '1', '20'),
            $this->line(1000, PriceMode::INCLUDING_TAX, '1', '20'),
            $this->line(1000, PriceMode::INCLUDING_TAX, '1', '20'),
        ]);

        self::assertSame([833, 833, 833], $this->nets($amounts));
        self::assertSame(2499, $amounts->taxBasis()->minorUnits());
        self::assertSame(500, $amounts->totalVat()->minorUnits());
        self::assertSame(2999, $amounts->totalGross()->minorUnits());
        self::assertSame('8.333333', $amounts->lines()[0]->unitNet()->toDecimalString());
        $this->assertEn16931($amounts, ['1', '1', '1']);
    }

    /**
     * @return iterable<string, array{CalculationMode}>
     */
    public static function modes(): iterable
    {
        yield 'incl first' => [CalculationMode::INCLUDING_TAX_FIRST];
        yield 'excl first' => [CalculationMode::EXCLUDING_TAX_FIRST];
    }

    public function testVeterinaryBasketIncludingTaxFirstChargesExactlyTheDisplayedPrices(): void
    {
        $amounts = $this->calculate(CalculationMode::INCLUDING_TAX_FIRST, $this->basket());

        self::assertSame(4500 + 2 * 1290 + 735 + 6499, $amounts->totalGross()->minorUnits());
        self::assertSame([4500, 2580, 735, 6499], $this->grosses($amounts));
        self::assertCount(2, $amounts->buckets());
        self::assertSame('20.0', $amounts->buckets()[0]->ratePercent());
        self::assertSame('5.5', $amounts->buckets()[1]->ratePercent());

        foreach ($amounts->buckets() as $bucket) {
            // TTC exact per rate; base = TTC / (1 + rate) rounded; VAT = the rest.
            self::assertSame($bucket->gross()->minorUnits(), $bucket->base()->minorUnits() + $bucket->vat()->minorUnits());
        }

        self::assertSame(3750, $amounts->buckets()[0]->base()->minorUnits());
        self::assertSame(750, $amounts->buckets()[0]->vat()->minorUnits());
        // 98.14 / 1.055 = 93.0236… → 93.02, VAT 5.12.
        self::assertSame(9302, $amounts->buckets()[1]->base()->minorUnits());
        self::assertSame(512, $amounts->buckets()[1]->vat()->minorUnits());
        $this->assertBrCo10And13($amounts);
    }

    public function testVeterinaryBasketExcludingTaxFirstHoldsEn16931Rules(): void
    {
        $amounts = $this->calculate(CalculationMode::EXCLUDING_TAX_FIRST, $this->basket());

        $this->assertEn16931($amounts, ['1', '2', '1', '1']);
        // 45 / 1.2 = 37.50; 12.90 / 1.055 = 12.227488 × 2 = 24.45; 7.35 → 6.966825 → 6.97; 64.99 → 61.601896 → 61.60.
        self::assertSame([3750, 2445, 697, 6160], $this->nets($amounts));
        self::assertSame(9302, $amounts->buckets()[1]->base()->minorUnits());
        self::assertSame(512, $amounts->buckets()[1]->vat()->minorUnits());
    }

    public function testExcludingTaxEnteredLineIsGrossedUpPerUnitIncludingTaxFirst(): void
    {
        // 10.00 € excl. at 5.5 % → 10.55 € per unit, × 3 = 31.65 €.
        $amounts = $this->calculate(CalculationMode::INCLUDING_TAX_FIRST, [
            $this->line(1000, PriceMode::EXCLUDING_TAX, '3', '5.5'),
            $this->line(1000, PriceMode::INCLUDING_TAX, '1', '5.5'),
        ]);

        self::assertSame([3165, 1000], $this->grosses($amounts));
        self::assertSame('10.000000', $amounts->lines()[0]->unitNet()->toDecimalString());
        self::assertSame(4165, $amounts->totalGross()->minorUnits());
        $this->assertBrCo10And13($amounts);
    }

    public function testMixedEntryModesExcludingTaxFirst(): void
    {
        $amounts = $this->calculate(CalculationMode::EXCLUDING_TAX_FIRST, [
            $this->line(1234, PriceMode::EXCLUDING_TAX, '1.5', '20'),
            $this->line(999, PriceMode::INCLUDING_TAX, '3', '20'),
        ]);

        // 1.5 × 12.34 = 18.51; 9.99 / 1.2 = 8.325 → 8.325000 × 3 = 24.975 → 24.98 (half even).
        self::assertSame([1851, 2498], $this->nets($amounts));
        self::assertSame('12.340000', $amounts->lines()[0]->unitNet()->toDecimalString());
        self::assertSame('8.325000', $amounts->lines()[1]->unitNet()->toDecimalString());
        // VAT = round(43.49 × 20 %) = 8.698 → 8.70.
        self::assertSame(870, $amounts->totalVat()->minorUnits());
        $this->assertEn16931($amounts, ['1.5', '3']);
    }

    public function testPercentageDiscountExcludingTaxFirst(): void
    {
        $amounts = $this->calculate(CalculationMode::EXCLUDING_TAX_FIRST, [
            $this->line(10000, PriceMode::EXCLUDING_TAX, '1', '20'),
            $this->line(3333, PriceMode::EXCLUDING_TAX, '1', '5.5'),
        ], Discount::percentage('10'));

        [$standard, $reduced] = $amounts->buckets();

        self::assertSame(1000, $standard->allowance()->minorUnits());
        self::assertSame(9000, $standard->base()->minorUnits());
        self::assertSame(1800, $standard->vat()->minorUnits());
        // 33.33 × 10 % = 3.333 → 3.33; base 30.00; VAT 1.65.
        self::assertSame(333, $reduced->allowance()->minorUnits());
        self::assertSame(3000, $reduced->base()->minorUnits());
        self::assertSame(165, $reduced->vat()->minorUnits());
        self::assertSame(13333, $amounts->totalLineNet()->minorUnits());
        self::assertSame(1333, $amounts->totalAllowance()->minorUnits());
        self::assertSame(12000, $amounts->taxBasis()->minorUnits());
        $this->assertEn16931($amounts, ['1', '1']);
    }

    public function testPercentageDiscountIncludingTaxFirst(): void
    {
        $amounts = $this->calculate(CalculationMode::INCLUDING_TAX_FIRST, [
            $this->line(12000, PriceMode::INCLUDING_TAX, '1', '20'),
            $this->line(1055, PriceMode::INCLUDING_TAX, '1', '5.5'),
        ], Discount::percentage('10'));

        [$standard, $reduced] = $amounts->buckets();

        // 120.00 → 108.00 incl., base 90.00, undiscounted base 100.00.
        self::assertSame(9000, $standard->base()->minorUnits());
        self::assertSame(1800, $standard->vat()->minorUnits());
        self::assertSame(1000, $standard->allowance()->minorUnits());
        // 10.55 − round(1.055) = 10.55 − 1.06 = 9.49 → base 9.00 (8.995… → 9.00), allowance 10.00 − 9.00.
        self::assertSame(949, $reduced->gross()->minorUnits());
        self::assertSame(100, $reduced->allowance()->minorUnits());
        self::assertSame(10800 + 949, $amounts->totalGross()->minorUnits());
        $this->assertBrCo10And13($amounts);
    }

    #[DataProvider('modes')]
    public function testFixedDiscountIsSplitOverRatesByLargestRemainder(CalculationMode $mode): void
    {
        $lines = [
            $this->line(6000, PriceMode::INCLUDING_TAX, '1', '20'),
            $this->line(4000, PriceMode::INCLUDING_TAX, '1', '5.5'),
        ];
        $undiscounted = $this->calculate($mode, $lines);
        $amounts      = $this->calculate($mode, $lines, Discount::fixed(Money::fromMinorUnits(1000, $this->eur())));

        $this->assertBrCo10And13($amounts);

        if (CalculationMode::INCLUDING_TAX_FIRST === $mode) {
            self::assertSame($undiscounted->totalGross()->minorUnits() - 1000, $amounts->totalGross()->minorUnits());
            self::assertSame(5400, $amounts->buckets()[0]->gross()->minorUnits());
            self::assertSame(3600, $amounts->buckets()[1]->gross()->minorUnits());

            return;
        }

        $this->assertEn16931($amounts, ['1', '1']);
        // Shares of 10.00 € over 59.99 € / 40.00 €: 6.00 / 4.00 → allowances 5.00 / 3.79.
        self::assertSame(500, $amounts->buckets()[0]->allowance()->minorUnits());
        self::assertSame(379, $amounts->buckets()[1]->allowance()->minorUnits());
    }

    #[DataProvider('modes')]
    public function testFixedDiscountIsCappedAtTheDocument(CalculationMode $mode): void
    {
        $amounts = $this->calculate($mode, [
            $this->line(1200, PriceMode::INCLUDING_TAX, '1', '20'),
        ], Discount::fixed(Money::fromMinorUnits(5000, $this->eur())));

        self::assertSame(0, $amounts->totalGross()->minorUnits());
        self::assertSame(1000, $amounts->totalAllowance()->minorUnits());
    }

    #[DataProvider('modes')]
    public function testHundredPercentDiscountLeavesNothingToPay(CalculationMode $mode): void
    {
        $amounts = $this->calculate($mode, [
            $this->line(1200, PriceMode::INCLUDING_TAX, '1', '20'),
        ], Discount::percentage('100'));

        self::assertSame(0, $amounts->totalGross()->minorUnits());
        self::assertSame(0, $amounts->totalVat()->minorUnits());
    }

    #[DataProvider('modes')]
    public function testNoLinesGivesNoAmounts(CalculationMode $mode): void
    {
        $amounts = $this->calculate($mode, [], Discount::percentage('10'));

        self::assertSame([], $amounts->lines());
        self::assertSame([], $amounts->buckets());
        self::assertSame(0, $amounts->totalGross()->minorUnits());
    }

    #[DataProvider('modes')]
    public function testZeroRateLinesCarryNoVat(CalculationMode $mode): void
    {
        $amounts = $this->calculate($mode, [
            $this->line(4599, PriceMode::INCLUDING_TAX, '2', '0'),
        ]);

        self::assertSame(9198, $amounts->totalGross()->minorUnits());
        self::assertSame(0, $amounts->totalVat()->minorUnits());
        self::assertSame([9198], $this->nets($amounts));
    }

    #[DataProvider('modes')]
    public function testRatesWrittenDifferentlyShareOneBucket(CalculationMode $mode): void
    {
        $amounts = $this->calculate($mode, [
            $this->line(1200, PriceMode::INCLUDING_TAX, '1', '20'),
            $this->line(1200, PriceMode::INCLUDING_TAX, '1', '20.00'),
        ]);

        self::assertCount(1, $amounts->buckets());
        self::assertSame('20', $amounts->buckets()[0]->ratePercent());
    }

    #[DataProvider('modes')]
    public function testDiscountIsIgnoredOnANegativeBucket(CalculationMode $mode): void
    {
        $amounts = $this->calculate($mode, [
            $this->line(1200, PriceMode::INCLUDING_TAX, '-1', '20'),
        ], Discount::percentage('10'));

        self::assertSame(0, $amounts->totalAllowance()->minorUnits());
        self::assertSame(-1200, $amounts->totalGross()->minorUnits());
    }

    public function testMalformedQuantityIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculate(CalculationMode::EXCLUDING_TAX_FIRST, [$this->line(100, PriceMode::EXCLUDING_TAX, 'two', '20')]);
    }

    /**
     * A typical veterinary basket: the consultation at the standard rate,
     * medicines and therapeutic food at the super-reduced rate (French
     * regime: veterinary.act.consultation 20 %, veterinary.drug.companion and
     * veterinary.food.therapeutic 5.5 %).
     *
     * @return list<CalculationLine>
     */
    private function basket(): array
    {
        return [
            $this->line(4500, PriceMode::INCLUDING_TAX, '1', '20.0'),
            $this->line(1290, PriceMode::INCLUDING_TAX, '2', '5.5'),
            $this->line(735, PriceMode::INCLUDING_TAX, '1', '5.5'),
            $this->line(6499, PriceMode::INCLUDING_TAX, '1', '5.5'),
        ];
    }

    /**
     * BR-CO-10: Σ line nets = BT-106; BR-CO-13: BT-109 = BT-106 − BT-107;
     * BG-23 adds up to the totals.
     */
    private function assertBrCo10And13(CalculatedAmounts $amounts): void
    {
        self::assertSame(array_sum($this->nets($amounts)), $amounts->totalLineNet()->minorUnits());
        self::assertSame(
            $amounts->totalLineNet()->minorUnits() - $amounts->totalAllowance()->minorUnits(),
            $amounts->taxBasis()->minorUnits(),
        );

        $perRate = array_map(static fn (RateBucket $bucket): int => $bucket->gross()->minorUnits(), $amounts->buckets());
        self::assertSame(array_sum($perRate), $amounts->totalGross()->minorUnits());
        self::assertSame($amounts->taxBasis()->minorUnits() + $amounts->totalVat()->minorUnits(), $amounts->totalGross()->minorUnits());
    }

    /**
     * BR-CO-10 / 13 plus BR-CO-17 (VAT per rate = base × rate, rounded) and
     * Peppol R120 (line net = quantity × unit net, rounded), recomputed
     * independently of the calculator.
     *
     * @param list<string> $quantities
     */
    private function assertEn16931(CalculatedAmounts $amounts, array $quantities): void
    {
        $this->assertBrCo10And13($amounts);

        foreach ($amounts->buckets() as $bucket) {
            /** @var numeric-string $rate */
            $rate     = $bucket->ratePercent();
            $expected = (int) bcround(
                bcdiv(bcmul((string) $bucket->base()->minorUnits(), $rate, 6), '100', 6),
                0,
                \RoundingMode::HalfEven,
            );
            self::assertSame($expected, $bucket->vat()->minorUnits(), 'BR-CO-17 at ' . $bucket->ratePercent() . ' %');
        }

        foreach ($amounts->lines() as $index => $line) {
            /** @var numeric-string $quantity */
            $quantity = $quantities[$index];
            /** @var numeric-string $unitNet */
            $unitNet  = $line->unitNet()->toDecimalString();
            $expected = (int) bcmul(bcround(bcmul($quantity, $unitNet, 8), 2, \RoundingMode::HalfEven), '100', 0);
            self::assertSame($expected, $line->net()->minorUnits(), 'R120 on line ' . $index);
        }
    }

    /**
     * @return list<int>
     */
    private function nets(CalculatedAmounts $amounts): array
    {
        return array_map(static fn ($line): int => $line->net()->minorUnits(), $amounts->lines());
    }

    /**
     * @return list<int>
     */
    private function grosses(CalculatedAmounts $amounts): array
    {
        return array_map(static fn ($line): int => $line->gross()->minorUnits(), $amounts->lines());
    }

    /**
     * @param list<CalculationLine> $lines
     */
    private function calculate(CalculationMode $mode, array $lines, ?Discount $discount = null): CalculatedAmounts
    {
        return new InvoiceAmountCalculator()->calculate($mode, $lines, $discount, $this->eur(), CurrencyDecimals::of(2));
    }

    private function line(int $price, PriceMode $mode, string $quantity, string $rate): CalculationLine
    {
        return new CalculationLine(Money::fromMinorUnits($price, $this->eur()), $mode, $quantity, $rate);
    }

    private function eur(): CurrencyCode
    {
        return CurrencyCode::fromString('EUR');
    }
}
