<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Service;

use App\Context\Billing\Domain\ValueObject\CalculatedAmounts;
use App\Context\Billing\Domain\ValueObject\CalculationLine;
use App\Context\Billing\Domain\ValueObject\CalculationMode;
use App\Context\Billing\Domain\ValueObject\Discount;
use App\Context\Billing\Domain\ValueObject\DiscountKind;
use App\Context\Billing\Domain\ValueObject\LineAmounts;
use App\Context\Billing\Domain\ValueObject\PriceMode;
use App\Context\Billing\Domain\ValueObject\RateBucket;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\Allocation\LargestRemainderAllocator;
use App\Shared\Money\Domain\ValueObject\CurrencyDecimals;
use App\Shared\Money\Domain\ValueObject\Money;
use App\Shared\Money\Domain\ValueObject\UnitPrice;

/**
 * Computes every amount of a document from the prices as entered.
 *
 * VAT is computed per rate, never per line, in one of two modes:
 *
 * - EXCLUDING_TAX_FIRST (professional buyers, EN16931 strict): unit net =
 *   the entered price, or entered / (1 + rate) at six decimals; line net =
 *   round(quantity × unit net); base = Σ line nets − allowance; VAT =
 *   round(base × rate). BR-CO-10 / 13 / 17 and BT-131 = BT-129 × BT-146 hold
 *   by construction. A price entered incl. VAT may come out one cent off:
 *   the counter shows the recalculated total before payment.
 * - INCLUDING_TAX_FIRST (private buyers): line TTC = round(quantity × unit
 *   price incl. VAT) — a price entered excl. VAT is first grossed up per unit
 *   to the cent; per rate, TTC = Σ line TTC (exact), base = round(TTC / (1 +
 *   rate)), VAT = TTC − base; line nets share the base by largest remainder.
 *
 * A document-level discount is an allowance (BG-20) split per rate; line nets
 * stay undiscounted. No rounding amount (BT-114) is ever produced.
 *
 * Pure: no I/O, no float, every rounding half to even.
 */
final class InvoiceAmountCalculator
{
    private const int SCALE = 12;

    /**
     * @param list<CalculationLine> $lines
     */
    public function calculate(
        CalculationMode $mode,
        array $lines,
        ?Discount $discount,
        CurrencyCode $currency,
        CurrencyDecimals $decimals,
    ): CalculatedAmounts {
        if ([] === $lines) {
            return CalculatedAmounts::none($currency);
        }

        $groups = $this->groupByRate($lines);

        $lineAmounts = [];
        $buckets     = [];
        $raw         = [];

        foreach ($groups as $group) {
            $computed = match ($mode) {
                CalculationMode::EXCLUDING_TAX_FIRST => $this->excludingTaxFirst($group['rate'], $group['lines'], $currency, $decimals),
                CalculationMode::INCLUDING_TAX_FIRST => $this->includingTaxFirst($group['rate'], $group['lines'], $currency, $decimals),
            };

            foreach ($computed['lines'] as $index => $amounts) {
                $lineAmounts[$index] = $amounts;
            }

            $raw[] = ['rate' => $group['rate'], 'lineTotal' => $computed['lineTotal'], 'gross' => $computed['gross'], 'vat' => $computed['vat']];
        }

        $fixedShares = $this->fixedShares($discount, $raw);

        foreach ($raw as $i => $bucket) {
            [$allowance, $vat] = $this->applyDiscount($mode, $discount, $fixedShares[$i] ?? 0, $bucket['rate'], $bucket['lineTotal'], $bucket['gross'], $bucket['vat']);

            $buckets[] = RateBucket::of($bucket['rate'], $bucket['lineTotal'], $allowance, $vat, $currency);
        }

        ksort($lineAmounts);

        return CalculatedAmounts::of(array_values($lineAmounts), $buckets, $currency);
    }

    /**
     * @param list<CalculationLine> $lines
     *
     * @return list<array{rate: string, lines: array<int, CalculationLine>}>
     */
    private function groupByRate(array $lines): array
    {
        $groups = [];

        foreach ($lines as $index => $line) {
            /** @var numeric-string $rate */
            $rate = $line->ratePercent;
            $key  = bcadd($rate, '0', 4);

            $groups[$key] ??= ['rate' => $line->ratePercent, 'lines' => []];
            $groups[$key]['lines'][$index] = $line;
        }

        return array_values($groups);
    }

    /**
     * @param array<int, CalculationLine> $lines
     *
     * @return array{lines: array<int, LineAmounts>, lineTotal: int, gross: int, vat: int}
     */
    private function excludingTaxFirst(string $rate, array $lines, CurrencyCode $currency, CurrencyDecimals $decimals): array
    {
        $nets     = [];
        $unitNets = [];

        foreach ($lines as $index => $line) {
            $unitNet = PriceMode::EXCLUDING_TAX === $line->priceMode
                ? UnitPrice::fromMoney($line->enteredUnitPrice, $decimals)
                : UnitPrice::netOfTax($line->enteredUnitPrice, $rate, $decimals);

            $unitNets[$index] = $unitNet;
            $nets[$index]     = $this->lineNet($line->quantity, $unitNet, $decimals);
        }

        $lineTotal = array_sum($nets);
        $vat       = $this->vatOn($lineTotal, $rate);
        $vatShares = $this->allocateOver($vat, $nets);

        $amounts = [];

        foreach ($nets as $index => $net) {
            $amounts[$index] = LineAmounts::of(
                Money::fromMinorUnits($net, $currency),
                Money::fromMinorUnits($net + $vatShares[$index], $currency),
                $unitNets[$index],
            );
        }

        return ['lines' => $amounts, 'lineTotal' => $lineTotal, 'gross' => $lineTotal + $vat, 'vat' => $vat];
    }

    /**
     * @param array<int, CalculationLine> $lines
     *
     * @return array{lines: array<int, LineAmounts>, lineTotal: int, gross: int, vat: int}
     */
    private function includingTaxFirst(string $rate, array $lines, CurrencyCode $currency, CurrencyDecimals $decimals): array
    {
        $grosses  = [];
        $unitNets = [];

        foreach ($lines as $index => $line) {
            $entered = $line->enteredUnitPrice->minorUnits();

            if (PriceMode::EXCLUDING_TAX === $line->priceMode) {
                $unitGross        = $entered + $this->vatOn($entered, $rate);
                $unitNets[$index] = UnitPrice::fromMoney($line->enteredUnitPrice, $decimals);
            } else {
                $unitGross        = $entered;
                $unitNets[$index] = UnitPrice::netOfTax($line->enteredUnitPrice, $rate, $decimals);
            }

            $grosses[$index] = $this->round(bcmul($this->numeric($line->quantity), (string) $unitGross, self::SCALE));
        }

        $gross = array_sum($grosses);
        $base  = $this->baseOf($gross, $rate);
        $nets  = $this->allocateOver($base, $grosses);

        $amounts = [];

        foreach ($grosses as $index => $lineGross) {
            $amounts[$index] = LineAmounts::of(
                Money::fromMinorUnits($nets[$index], $currency),
                Money::fromMinorUnits($lineGross, $currency),
                $unitNets[$index],
            );
        }

        return ['lines' => $amounts, 'lineTotal' => $base, 'gross' => $gross, 'vat' => $gross - $base];
    }

    /**
     * A fixed discount is an amount incl. VAT, as shown to the client: it is
     * split over the rates in proportion to what each rate weighs incl. VAT,
     * never more than the document is worth.
     *
     * @param list<array{rate: string, lineTotal: int, gross: int, vat: int}> $buckets
     *
     * @return list<int>
     */
    private function fixedShares(?Discount $discount, array $buckets): array
    {
        if (null === $discount || DiscountKind::FIXED !== $discount->kind()) {
            return [];
        }

        $weights = array_map(static fn (array $bucket): int => max(0, $bucket['gross']), $buckets);
        $amount  = min((int) $discount->value(), array_sum($weights));

        return LargestRemainderAllocator::allocate($amount, $weights);
    }

    /**
     * @return array{int, int} the allowance and the VAT of the bucket after the discount
     */
    private function applyDiscount(
        CalculationMode $mode,
        ?Discount $discount,
        int $fixedShare,
        string $rate,
        int $lineTotal,
        int $gross,
        int $vat,
    ): array {
        if (null === $discount || $lineTotal <= 0) {
            return [0, $vat];
        }

        if (CalculationMode::EXCLUDING_TAX_FIRST === $mode) {
            $allowance = match ($discount->kind()) {
                DiscountKind::PERCENTAGE => $this->percentOf($lineTotal, $discount->value()),
                DiscountKind::FIXED      => $this->baseOf($fixedShare, $rate),
            };
            $allowance = min($allowance, $lineTotal);

            return [$allowance, $this->vatOn($lineTotal - $allowance, $rate)];
        }

        $off = match ($discount->kind()) {
            DiscountKind::PERCENTAGE => $this->percentOf($gross, $discount->value()),
            DiscountKind::FIXED      => $fixedShare,
        };
        $discountedGross = $gross - min($off, $gross);
        $base            = $this->baseOf($discountedGross, $rate);

        return [$lineTotal - $base, $discountedGross - $base];
    }

    private function lineNet(string $quantity, UnitPrice $unitNet, CurrencyDecimals $decimals): int
    {
        $microPerMinor = (string) (10 ** (6 - $decimals->value()));
        $micro         = bcmul($this->numeric($quantity), (string) $unitNet->microUnits(), self::SCALE);

        return $this->round(bcdiv($micro, $microPerMinor, self::SCALE));
    }

    private function vatOn(int $amount, string $ratePercent): int
    {
        return $this->round(bcdiv(bcmul((string) $amount, $this->numeric($ratePercent), self::SCALE), '100', self::SCALE));
    }

    private function percentOf(int $amount, string $percent): int
    {
        return $this->vatOn($amount, $percent);
    }

    private function baseOf(int $gross, string $ratePercent): int
    {
        $divisor = bcadd('1', bcdiv($this->numeric($ratePercent), '100', self::SCALE), self::SCALE);

        return $this->round(bcdiv((string) $gross, $divisor, self::SCALE));
    }

    /**
     * @param array<int, int> $weights
     *
     * @return array<int, int> the shares, keyed like the weights
     */
    private function allocateOver(int $amount, array $weights): array
    {
        $shares = LargestRemainderAllocator::allocate(
            $amount,
            array_values(array_map(static fn (int $weight): int => abs($weight), $weights)),
        );

        return array_combine(array_keys($weights), $shares);
    }

    /**
     * @param numeric-string $value
     */
    private function round(string $value): int
    {
        return (int) bcround($value, 0, \RoundingMode::HalfEven);
    }

    /**
     * @return numeric-string
     */
    private function numeric(string $value): string
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException(\sprintf('Expected a decimal, got "%s".', $value));
        }

        return $value;
    }
}
