<?php

declare(strict_types=1);

namespace App\System\Money\Domain\Service;

use App\System\Money\Domain\Exception\AllocationException;
use App\System\Money\Domain\Exception\CurrencyMismatchException;
use App\System\Money\Domain\RoundingPolicy\RoundingPolicy;
use App\System\Money\Domain\ValueObject\Money;

/**
 * Arithmetic operations on monetary amounts.
 *
 * All operations use bcmath exclusively (zero floats).
 * add() and subtract() operate on integer minor units directly.
 * multiply(), divide(), percentage() and applyCoefficient() convert to decimal,
 * apply the provided RoundingPolicy, then convert back to minor units.
 * allocate() distributes minor units exactly (remainder to last part) —
 * no policy needed since the result is always exact integers.
 */
final class MoneyCalculator
{
    public function __construct(private readonly CurrencyRegistry $currencyRegistry)
    {
    }

    public function add(Money $a, Money $b): Money
    {
        if (!$a->currency()->equals($b->currency())) {
            throw new CurrencyMismatchException($a->currency()->toString(), $b->currency()->toString());
        }

        return Money::fromMinorUnits($a->minorUnits() + $b->minorUnits(), $a->currency());
    }

    public function subtract(Money $a, Money $b): Money
    {
        if (!$a->currency()->equals($b->currency())) {
            throw new CurrencyMismatchException($a->currency()->toString(), $b->currency()->toString());
        }

        return Money::fromMinorUnits($a->minorUnits() - $b->minorUnits(), $a->currency());
    }

    public function multiply(Money $money, string $factor, RoundingPolicy $rounding): Money
    {
        $currency = $this->currencyRegistry->get($money->currency());
        $decimals = $currency->decimals();
        $scale    = $decimals->value() + 4;

        $multiplier = (string) (10 ** $decimals->value());
        /** @var numeric-string $decimalAmount */
        $decimalAmount = bcdiv((string) $money->minorUnits(), $multiplier, $scale);
        /** @var numeric-string $factor */
        /** @var numeric-string $result */
        $result = bcmul($decimalAmount, $factor, $scale);
        /** @var numeric-string $rounded */
        $rounded = $rounding->round($result, $money->currency(), $decimals);

        return Money::fromMinorUnits((int) bcmul($rounded, $multiplier, 0), $money->currency());
    }

    public function divide(Money $money, string $divisor, RoundingPolicy $rounding): Money
    {
        $currency = $this->currencyRegistry->get($money->currency());
        $decimals = $currency->decimals();
        $scale    = $decimals->value() + 4;

        $multiplier = (string) (10 ** $decimals->value());
        /** @var numeric-string $decimalAmount */
        $decimalAmount = bcdiv((string) $money->minorUnits(), $multiplier, $scale);
        /** @var numeric-string $divisor */
        /** @var numeric-string $result */
        $result = bcdiv($decimalAmount, $divisor, $scale);
        /** @var numeric-string $rounded */
        $rounded = $rounding->round($result, $money->currency(), $decimals);

        return Money::fromMinorUnits((int) bcmul($rounded, $multiplier, 0), $money->currency());
    }

    public function percentage(Money $money, string $percent, RoundingPolicy $rounding): Money
    {
        return $this->multiply($money, bcdiv($percent, '100', 10), $rounding);
    }

    public function applyCoefficient(Money $money, string $coefficient, RoundingPolicy $rounding): Money
    {
        return $this->multiply($money, $coefficient, $rounding);
    }

    /**
     * @param list<int> $ratios
     *
     * @return list<Money>
     */
    public function allocate(Money $money, array $ratios): array
    {
        if ([] === $ratios) {
            throw new AllocationException('ratios array is empty');
        }

        $total = (int) array_sum($ratios);

        if (0 === $total) {
            throw new AllocationException('all ratios are zero');
        }

        $results   = [];
        $allocated = 0;
        $lastIndex = \count($ratios) - 1;

        foreach ($ratios as $index => $ratio) {
            if ($index === $lastIndex) {
                $results[] = Money::fromMinorUnits($money->minorUnits() - $allocated, $money->currency());
            } else {
                $share = (int) bcround(
                    bcdiv(
                        bcmul((string) $money->minorUnits(), (string) $ratio, 10),
                        (string) $total,
                        10,
                    ),
                    0,
                    \RoundingMode::HalfAwayFromZero,
                );
                $results[] = Money::fromMinorUnits($share, $money->currency());
                $allocated += $share;
            }
        }

        return $results;
    }

    public function abs(Money $money): Money
    {
        return Money::fromMinorUnits(abs($money->minorUnits()), $money->currency());
    }

    public function neg(Money $money): Money
    {
        return Money::fromMinorUnits(-$money->minorUnits(), $money->currency());
    }
}
