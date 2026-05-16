<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Money\Domain\Service;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\Exception\CurrencyMismatchException;
use App\Shared\Money\Domain\Service\MoneyComparator;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class MoneyComparatorTest extends TestCase
{
    private MoneyComparator $comparator;
    private CurrencyCode $eur;
    private CurrencyCode $chf;

    protected function setUp(): void
    {
        $this->comparator = new MoneyComparator();
        $this->eur        = CurrencyCode::fromString('EUR');
        $this->chf        = CurrencyCode::fromString('CHF');
    }

    public function testEqualsReturnsTrueForSameAmountAndCurrency(): void
    {
        $a = Money::fromMinorUnits(100, $this->eur);
        $b = Money::fromMinorUnits(100, $this->eur);

        self::assertTrue($this->comparator->equals($a, $b));
    }

    public function testEqualsReturnsFalseForDifferentCurrency(): void
    {
        // AC-05b: no exception, just returns false
        $a = Money::fromMinorUnits(100, $this->eur);
        $b = Money::fromMinorUnits(100, $this->chf);

        self::assertFalse($this->comparator->equals($a, $b));
    }

    public function testIsGreaterThan(): void
    {
        $a = Money::fromMinorUnits(200, $this->eur);
        $b = Money::fromMinorUnits(100, $this->eur);

        self::assertTrue($this->comparator->isGreaterThan($a, $b));
        self::assertFalse($this->comparator->isGreaterThan($b, $a));
    }

    public function testIsGreaterThanThrowsOnDifferentCurrency(): void
    {
        $a = Money::fromMinorUnits(200, $this->eur);
        $b = Money::fromMinorUnits(100, $this->chf);

        $this->expectException(CurrencyMismatchException::class);

        $this->comparator->isGreaterThan($a, $b);
    }

    public function testIsLessThan(): void
    {
        $a = Money::fromMinorUnits(100, $this->eur);
        $b = Money::fromMinorUnits(200, $this->eur);

        self::assertTrue($this->comparator->isLessThan($a, $b));
        self::assertFalse($this->comparator->isLessThan($b, $a));
    }

    public function testIsLessThanThrowsOnDifferentCurrency(): void
    {
        $a = Money::fromMinorUnits(100, $this->eur);
        $b = Money::fromMinorUnits(200, $this->chf);

        $this->expectException(CurrencyMismatchException::class);

        $this->comparator->isLessThan($a, $b);
    }

    public function testIsGreaterThanOrEqual(): void
    {
        $a = Money::fromMinorUnits(200, $this->eur);
        $b = Money::fromMinorUnits(200, $this->eur);
        $c = Money::fromMinorUnits(100, $this->eur);

        self::assertTrue($this->comparator->isGreaterThanOrEqual($a, $b));
        self::assertTrue($this->comparator->isGreaterThanOrEqual($a, $c));
        self::assertFalse($this->comparator->isGreaterThanOrEqual($c, $a));
    }

    public function testIsGreaterThanOrEqualThrowsOnDifferentCurrency(): void
    {
        $a = Money::fromMinorUnits(200, $this->eur);
        $b = Money::fromMinorUnits(200, $this->chf);

        $this->expectException(CurrencyMismatchException::class);

        $this->comparator->isGreaterThanOrEqual($a, $b);
    }

    public function testIsLessThanOrEqual(): void
    {
        $a = Money::fromMinorUnits(100, $this->eur);
        $b = Money::fromMinorUnits(100, $this->eur);
        $c = Money::fromMinorUnits(200, $this->eur);

        self::assertTrue($this->comparator->isLessThanOrEqual($a, $b));
        self::assertTrue($this->comparator->isLessThanOrEqual($a, $c));
        self::assertFalse($this->comparator->isLessThanOrEqual($c, $a));
    }

    public function testIsLessThanOrEqualThrowsOnDifferentCurrency(): void
    {
        $a = Money::fromMinorUnits(100, $this->eur);
        $b = Money::fromMinorUnits(200, $this->chf);

        $this->expectException(CurrencyMismatchException::class);

        $this->comparator->isLessThanOrEqual($a, $b);
    }

    public function testMaxReturnsLargest(): void
    {
        $a = Money::fromMinorUnits(100, $this->eur);
        $b = Money::fromMinorUnits(300, $this->eur);
        $c = Money::fromMinorUnits(200, $this->eur);

        $max = $this->comparator->max($a, $b, $c);

        self::assertSame(300, $max->minorUnits());
    }

    public function testMaxThrowsOnEmpty(): void
    {
        // AC-05c
        $this->expectException(\InvalidArgumentException::class);

        $this->comparator->max();
    }

    public function testMaxThrowsOnMixedCurrencies(): void
    {
        $a = Money::fromMinorUnits(100, $this->eur);
        $b = Money::fromMinorUnits(200, $this->chf);

        $this->expectException(\InvalidArgumentException::class);

        $this->comparator->max($a, $b);
    }

    public function testMinReturnsSmallest(): void
    {
        $a = Money::fromMinorUnits(300, $this->eur);
        $b = Money::fromMinorUnits(100, $this->eur);
        $c = Money::fromMinorUnits(200, $this->eur);

        $min = $this->comparator->min($a, $b, $c);

        self::assertSame(100, $min->minorUnits());
    }

    public function testMinThrowsOnEmpty(): void
    {
        // AC-05c
        $this->expectException(\InvalidArgumentException::class);

        $this->comparator->min();
    }

    public function testMinThrowsOnMixedCurrencies(): void
    {
        $a = Money::fromMinorUnits(100, $this->eur);
        $b = Money::fromMinorUnits(200, $this->chf);

        $this->expectException(\InvalidArgumentException::class);

        $this->comparator->min($a, $b);
    }
}
