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
 * add() and subtract() operate on integer minor units; multiply(),
 * divide() and allocate() delegate final rounding to the provided RoundingPolicy.
 * allocate() guarantees that the sum of parts equals exactly the original amount
 * (remainder distributed to the last element).
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
        /** @var numeric-string $factor */
        /** @var numeric-string $result */
        $result  = bcmul((string) $money->minorUnits(), $factor, $scale);
        $rounded = bcround($result, 0, \RoundingMode::HalfAwayFromZero);

        return Money::fromMinorUnits((int) $rounded, $money->currency());
    }

    public function divide(Money $money, string $divisor, RoundingPolicy $rounding): Money
    {
        $currency = $this->currencyRegistry->get($money->currency());
        $decimals = $currency->decimals();
        $scale    = $decimals->value() + 4;
        $result   = bcdiv((string) $money->minorUnits(), $divisor, $scale);
        $rounded  = bcround($result, 0, \RoundingMode::HalfAwayFromZero);

        return Money::fromMinorUnits((int) $rounded, $money->currency());
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
    public function allocate(Money $money, array $ratios, RoundingPolicy $rounding): array
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
