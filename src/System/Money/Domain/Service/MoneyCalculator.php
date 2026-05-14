<?php

declare(strict_types=1);

namespace App\System\Money\Domain\Service;

use App\System\Money\Domain\Exception\AllocationException;
use App\System\Money\Domain\Exception\CurrencyMismatchException;
use App\System\Money\Domain\RoundingPolicy\RoundingPolicy;
use App\System\Money\Domain\ValueObject\Money;

/**
 * Performs arithmetic operations on Money values using bcmath.
 *
 * Pure domain service: no I/O, no framework, no state.
 *
 * All operations preserve the invariant that Money is immutable: every
 * method returns a new Money instance. Currency mismatches always throw
 * CurrencyMismatchException; operations that produce decimals always
 * require an explicit RoundingPolicy.
 *
 * Internal arithmetic precision (BCMATH_SCALE) is set high enough to
 * absorb intermediate rounding noise; final rounding to the currency's
 * minor units precision is delegated to the RoundingPolicy.
 */
final class MoneyCalculator
{
    private const int BCMATH_SCALE = 12;

    public function __construct(private readonly CurrencyRegistry $currencyRegistry)
    {
    }

    public function add(Money $a, Money $b): Money
    {
        $this->assertSameCurrency($a, $b);

        return Money::fromMinorUnits($a->minorUnits() + $b->minorUnits(), $a->currency());
    }

    public function subtract(Money $a, Money $b): Money
    {
        $this->assertSameCurrency($a, $b);

        return Money::fromMinorUnits($a->minorUnits() - $b->minorUnits(), $a->currency());
    }

    /** @param numeric-string $factor */
    public function multiply(Money $money, string $factor, RoundingPolicy $rounding): Money
    {
        $this->assertValidDecimalString($factor, 'factor');

        return $this->applyArithmetic(
            $money,
            static fn (string $decimal) => bcmul($decimal, $factor, self::BCMATH_SCALE),
            $rounding,
        );
    }

    /** @param numeric-string $divisor */
    public function divide(Money $money, string $divisor, RoundingPolicy $rounding): Money
    {
        $this->assertValidDecimalString($divisor, 'divisor');

        /** @var numeric-string $divisor */
        if (0 === bccomp($divisor, '0', 10)) {
            throw new \DivisionByZeroError('Division by zero.');
        }

        return $this->applyArithmetic(
            $money,
            static fn (string $decimal) => bcdiv($decimal, $divisor, self::BCMATH_SCALE),
            $rounding,
        );
    }

    /** @param numeric-string $percent */
    public function percentage(Money $money, string $percent, RoundingPolicy $rounding): Money
    {
        $this->assertValidDecimalString($percent, 'percent');

        return $this->multiply($money, bcdiv($percent, '100', self::BCMATH_SCALE), $rounding);
    }

    /** @param numeric-string $coefficient */
    public function applyCoefficient(Money $money, string $coefficient, RoundingPolicy $rounding): Money
    {
        $this->assertValidDecimalString($coefficient, 'coefficient');

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
                        bcmul((string) $money->minorUnits(), (string) $ratio, self::BCMATH_SCALE),
                        (string) $total,
                        self::BCMATH_SCALE,
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

    private function assertSameCurrency(Money $a, Money $b): void
    {
        if (!$a->currency()->equals($b->currency())) {
            throw new CurrencyMismatchException($a->currency()->toString(), $b->currency()->toString());
        }
    }

    private function assertValidDecimalString(string $value, string $label): void
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException(
                \sprintf('Expected a numeric string for %s, got "%s".', $label, $value),
            );
        }
    }

    /**
     * Converts $money to its decimal representation, applies $op at the
     * appropriate bcmath scale, rounds via $rounding, and returns the result
     * as a new Money in minor units.
     *
     * @param \Closure(numeric-string): numeric-string $op
     */
    private function applyArithmetic(Money $money, \Closure $op, RoundingPolicy $rounding): Money
    {
        $decimals   = $this->currencyRegistry->get($money->currency())->decimals();
        $multiplier = (string) (10 ** $decimals->value());

        /** @var numeric-string $decimal */
        $decimal = bcdiv((string) $money->minorUnits(), $multiplier, self::BCMATH_SCALE);
        /** @var numeric-string $result */
        $result = $op($decimal);
        /** @var numeric-string $rounded */
        $rounded = $rounding->round($result, $money->currency(), $decimals);

        return Money::fromMinorUnits((int) bcmul($rounded, $multiplier, 0), $money->currency());
    }
}
