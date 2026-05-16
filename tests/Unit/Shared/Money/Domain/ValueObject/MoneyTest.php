<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Money\Domain\ValueObject;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\CurrencyDecimals;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testFromMinorUnitsAndAccessors(): void
    {
        $eur   = CurrencyCode::fromString('EUR');
        $money = Money::fromMinorUnits(1850, $eur);

        self::assertSame(1850, $money->minorUnits());
        self::assertSame('EUR', $money->currency()->toString());
    }

    public function testZeroIsZero(): void
    {
        $eur  = CurrencyCode::fromString('EUR');
        $zero = Money::zero($eur);

        self::assertTrue($zero->isZero());
        self::assertFalse($zero->isPositive());
        self::assertFalse($zero->isNegative());
    }

    public function testFromDecimalStringConvertsCorrectly(): void
    {
        $eur      = CurrencyCode::fromString('EUR');
        $decimals = CurrencyDecimals::of(2);
        $money    = Money::fromDecimalString('18.50', $eur, $decimals);

        self::assertSame(1850, $money->minorUnits());
        self::assertSame('EUR', $money->currency()->toString());
    }

    public function testFromDecimalStringThrowsOnExcessDecimalPlaces(): void
    {
        $eur      = CurrencyCode::fromString('EUR');
        $decimals = CurrencyDecimals::of(2);

        $this->expectException(\InvalidArgumentException::class);

        Money::fromDecimalString('18.505', $eur, $decimals);
    }

    public function testFromDecimalStringThrowsOnInvalidFormat(): void
    {
        $eur      = CurrencyCode::fromString('EUR');
        $decimals = CurrencyDecimals::of(2);

        $this->expectException(\InvalidArgumentException::class);

        Money::fromDecimalString('abc', $eur, $decimals);
    }

    public function testToDecimalString(): void
    {
        $eur      = CurrencyCode::fromString('EUR');
        $decimals = CurrencyDecimals::of(2);
        $money    = Money::fromMinorUnits(1850, $eur);

        self::assertSame('18.50', $money->toDecimalString($decimals));
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $eur = CurrencyCode::fromString('EUR');
        $a   = Money::fromMinorUnits(100, $eur);
        $b   = Money::fromMinorUnits(100, $eur);

        self::assertTrue($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentAmount(): void
    {
        $eur = CurrencyCode::fromString('EUR');
        $a   = Money::fromMinorUnits(100, $eur);
        $b   = Money::fromMinorUnits(200, $eur);

        self::assertFalse($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentCurrency(): void
    {
        $eur = CurrencyCode::fromString('EUR');
        $chf = CurrencyCode::fromString('CHF');

        $a = Money::fromMinorUnits(100, $eur);
        $b = Money::fromMinorUnits(100, $chf);

        // AC-05b: silently returns false, no exception
        self::assertFalse($a->equals($b));
    }
}
