<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Money\Domain\ValueObject;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\CurrencyDecimals;
use App\Shared\Money\Domain\ValueObject\Money;
use App\Shared\Money\Domain\ValueObject\UnitPrice;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UnitPriceTest extends TestCase
{
    public function testFromMicroUnitsKeepsTheValue(): void
    {
        $price = UnitPrice::fromMicroUnits(8_333_333, $this->eur());

        self::assertSame(8_333_333, $price->microUnits());
        self::assertSame('EUR', $price->currency()->toString());
        self::assertSame('8.333333', $price->toDecimalString());
    }

    public function testFromMoneyScalesMinorUnitsByTheCurrencyDecimals(): void
    {
        self::assertSame(12_340_000, UnitPrice::fromMoney(Money::fromMinorUnits(1234, $this->eur()), CurrencyDecimals::of(2))->microUnits());
        self::assertSame(1_234_000_000, UnitPrice::fromMoney(Money::fromMinorUnits(1234, CurrencyCode::fromString('JPY')), CurrencyDecimals::of(0))->microUnits());
        self::assertSame(1_234_000, UnitPrice::fromMoney(Money::fromMinorUnits(1234, CurrencyCode::fromString('KWD')), CurrencyDecimals::of(3))->microUnits());
    }

    public function testFromDecimalStringParsesExactly(): void
    {
        self::assertSame(8_333_333, UnitPrice::fromDecimalString('8.333333', $this->eur())->microUnits());
        self::assertSame(10_000_000, UnitPrice::fromDecimalString('10', $this->eur())->microUnits());
        self::assertSame(-1_500_000, UnitPrice::fromDecimalString('-1.5', $this->eur())->microUnits());
        // Beyond what a float represents exactly.
        self::assertSame(9_007_199_254_740_993, UnitPrice::fromDecimalString('9007199254.740993', $this->eur())->microUnits());
    }

    #[DataProvider('provideFromDecimalStringRefusesMalformedInputCases')]
    public function testFromDecimalStringRefusesMalformedInput(string $input): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UnitPrice::fromDecimalString($input, $this->eur());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideFromDecimalStringRefusesMalformedInputCases(): iterable
    {
        yield 'seven decimals' => ['1.1234567'];
        yield 'comma' => ['1,5'];
        yield 'empty' => [''];
        yield 'exponent' => ['1e3'];
    }

    public function testNetOfTaxDividesTheGrossPriceToSixDecimals(): void
    {
        $decimals = CurrencyDecimals::of(2);

        self::assertSame('8.333333', UnitPrice::netOfTax(Money::fromMinorUnits(1000, $this->eur()), '20', $decimals)->toDecimalString());
        self::assertSame('42.654028', UnitPrice::netOfTax(Money::fromMinorUnits(4500, $this->eur()), '5.5', $decimals)->toDecimalString());
        self::assertSame('45.000000', UnitPrice::netOfTax(Money::fromMinorUnits(4500, $this->eur()), '0', $decimals)->toDecimalString());
    }

    public function testNetOfTaxRoundsHalfToEven(): void
    {
        $decimals = CurrencyDecimals::of(4);

        // 100 micro-units / 8 = 12.5 → 12; 300 / 8 = 37.5 → 38.
        self::assertSame(12, UnitPrice::netOfTax(Money::fromMinorUnits(1, $this->eur()), '700', $decimals)->microUnits());
        self::assertSame(38, UnitPrice::netOfTax(Money::fromMinorUnits(3, $this->eur()), '700', $decimals)->microUnits());
        // 0.01 / 3 = 0.0033333… → 3 333 micro-units.
        self::assertSame(3333, UnitPrice::netOfTax(Money::fromMinorUnits(1, $this->eur()), '200', CurrencyDecimals::of(2))->microUnits());
    }

    public function testNetOfTaxRefusesAMalformedRate(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UnitPrice::netOfTax(Money::fromMinorUnits(1000, $this->eur()), '20%', CurrencyDecimals::of(2));
    }

    public function testToDecimalStringKeepsTrailingZerosAndRoundsHalfToEven(): void
    {
        $price = UnitPrice::fromDecimalString('8.3', $this->eur());

        self::assertSame('8.300000', $price->toDecimalString());
        self::assertSame('8.3000', $price->toDecimalString(4));
        self::assertSame('8', $price->toDecimalString(0));
        self::assertSame('0.12', UnitPrice::fromDecimalString('0.125', $this->eur())->toDecimalString(2));
        self::assertSame('0.14', UnitPrice::fromDecimalString('0.135', $this->eur())->toDecimalString(2));
        self::assertSame('-1.50', UnitPrice::fromDecimalString('-1.5', $this->eur())->toDecimalString(2));
    }

    public function testToDecimalStringRefusesAScaleOutOfRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UnitPrice::fromMicroUnits(1, $this->eur())->toDecimalString(7);
    }

    public function testToDecimalStringRefusesANegativeScale(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UnitPrice::fromMicroUnits(1, $this->eur())->toDecimalString(-1);
    }

    public function testEqualsComparesAmountAndCurrency(): void
    {
        $price = UnitPrice::fromMicroUnits(100, $this->eur());

        self::assertTrue($price->equals(UnitPrice::fromMicroUnits(100, $this->eur())));
        self::assertFalse($price->equals(UnitPrice::fromMicroUnits(101, $this->eur())));
        self::assertFalse($price->equals(UnitPrice::fromMicroUnits(100, CurrencyCode::fromString('USD'))));
    }

    private function eur(): CurrencyCode
    {
        return CurrencyCode::fromString('EUR');
    }
}
