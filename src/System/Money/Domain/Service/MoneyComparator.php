<?php

declare(strict_types=1);

namespace App\System\Money\Domain\Service;

use App\System\Money\Domain\Exception\CurrencyMismatchException;
use App\System\Money\Domain\ValueObject\Money;

final class MoneyComparator
{
    public function equals(Money $a, Money $b): bool
    {
        return $a->equals($b);
    }

    public function isGreaterThan(Money $a, Money $b): bool
    {
        if (!$a->currency()->equals($b->currency())) {
            throw new CurrencyMismatchException($a->currency()->toString(), $b->currency()->toString());
        }

        return $a->minorUnits() > $b->minorUnits();
    }

    public function isLessThan(Money $a, Money $b): bool
    {
        if (!$a->currency()->equals($b->currency())) {
            throw new CurrencyMismatchException($a->currency()->toString(), $b->currency()->toString());
        }

        return $a->minorUnits() < $b->minorUnits();
    }

    public function isGreaterThanOrEqual(Money $a, Money $b): bool
    {
        if (!$a->currency()->equals($b->currency())) {
            throw new CurrencyMismatchException($a->currency()->toString(), $b->currency()->toString());
        }

        return $a->minorUnits() >= $b->minorUnits();
    }

    public function isLessThanOrEqual(Money $a, Money $b): bool
    {
        if (!$a->currency()->equals($b->currency())) {
            throw new CurrencyMismatchException($a->currency()->toString(), $b->currency()->toString());
        }

        return $a->minorUnits() <= $b->minorUnits();
    }

    public function max(Money ...$amounts): Money
    {
        if ([] === $amounts) {
            throw new \InvalidArgumentException('Cannot find max of empty list.');
        }

        $first = $amounts[0];

        foreach (\array_slice($amounts, 1) as $amount) {
            if (!$first->currency()->equals($amount->currency())) {
                throw new \InvalidArgumentException('Cannot compare Money values with different currencies.');
            }
        }

        $max = $first;

        foreach (\array_slice($amounts, 1) as $amount) {
            if ($amount->minorUnits() > $max->minorUnits()) {
                $max = $amount;
            }
        }

        return $max;
    }

    public function min(Money ...$amounts): Money
    {
        if ([] === $amounts) {
            throw new \InvalidArgumentException('Cannot find min of empty list.');
        }

        $first = $amounts[0];

        foreach (\array_slice($amounts, 1) as $amount) {
            if (!$first->currency()->equals($amount->currency())) {
                throw new \InvalidArgumentException('Cannot compare Money values with different currencies.');
            }
        }

        $min = $first;

        foreach (\array_slice($amounts, 1) as $amount) {
            if ($amount->minorUnits() < $min->minorUnits()) {
                $min = $amount;
            }
        }

        return $min;
    }
}
