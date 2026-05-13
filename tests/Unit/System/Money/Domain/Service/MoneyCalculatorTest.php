<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Money\Domain\Service;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\Currency;
use App\System\Money\Domain\Exception\CurrencyMismatchException;
use App\System\Money\Domain\RoundingPolicy\CommercialRounding;
use App\System\Money\Domain\Service\CurrencyRegistry;
use App\System\Money\Domain\Service\MoneyCalculator;
use App\System\Money\Domain\ValueObject\CurrencyDecimals;
use App\System\Money\Domain\ValueObject\CurrencySymbol;
use App\System\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class MoneyCalculatorTest extends TestCase
{
    private MoneyCalculator $calculator;
    private CurrencyCode $eur;
    private CurrencyCode $chf;

    protected function setUp(): void
    {
        $this->eur = CurrencyCode::fromString('EUR');
        $this->chf = CurrencyCode::fromString('CHF');

        $now             = new \DateTimeImmutable('2026-05-14T10:00:00+00:00');
        $eurCurrencyData = Currency::reconstitute(
            code: $this->eur,
            symbol: CurrencySymbol::fromString('€'),
            decimals: CurrencyDecimals::of(2),
            displayName: 'Euro',
            active: true,
            createdAt: $now,
            updatedAt: $now,
        );
        $chfCurrencyData = Currency::reconstitute(
            code: $this->chf,
            symbol: CurrencySymbol::fromString('CHF'),
            decimals: CurrencyDecimals::of(2),
            displayName: 'Swiss Franc',
            active: true,
            createdAt: $now,
            updatedAt: $now,
        );

        $registry = $this->createStub(CurrencyRegistry::class);
        $registry->method('get')->willReturnCallback(
            static function (CurrencyCode $code) use ($eurCurrencyData, $chfCurrencyData): Currency {
                if ('EUR' === $code->toString()) {
                    return $eurCurrencyData;
                }

                return $chfCurrencyData;
            },
        );

        $this->calculator = new MoneyCalculator($registry);
    }

    public function testAddSameCurrency(): void
    {
        $a      = Money::fromMinorUnits(100, $this->eur);
        $b      = Money::fromMinorUnits(200, $this->eur);
        $result = $this->calculator->add($a, $b);

        self::assertSame(300, $result->minorUnits());
        self::assertSame('EUR', $result->currency()->toString());
    }

    public function testAddThrowsCurrencyMismatchException(): void
    {
        $a = Money::fromMinorUnits(100, $this->eur);
        $b = Money::fromMinorUnits(100, $this->chf);

        $this->expectException(CurrencyMismatchException::class);

        $this->calculator->add($a, $b);
    }

    public function testSubtractSameCurrency(): void
    {
        $a      = Money::fromMinorUnits(300, $this->eur);
        $b      = Money::fromMinorUnits(100, $this->eur);
        $result = $this->calculator->subtract($a, $b);

        self::assertSame(200, $result->minorUnits());
        self::assertSame('EUR', $result->currency()->toString());
    }

    public function testSubtractThrowsCurrencyMismatchException(): void
    {
        $a = Money::fromMinorUnits(300, $this->eur);
        $b = Money::fromMinorUnits(100, $this->chf);

        $this->expectException(CurrencyMismatchException::class);

        $this->calculator->subtract($a, $b);
    }

    public function testMultiply(): void
    {
        $money    = Money::fromMinorUnits(1000, $this->eur); // 10.00 EUR
        $rounding = new CommercialRounding();
        $result   = $this->calculator->multiply($money, '1.5', $rounding);

        self::assertSame(1500, $result->minorUnits());
        self::assertSame('EUR', $result->currency()->toString());
    }

    public function testDivide(): void
    {
        $money    = Money::fromMinorUnits(1000, $this->eur); // 10.00 EUR
        $rounding = new CommercialRounding();
        $result   = $this->calculator->divide($money, '4', $rounding);

        self::assertSame(250, $result->minorUnits());
    }

    public function testPercentage(): void
    {
        $money    = Money::fromMinorUnits(10000, $this->eur); // 100.00 EUR
        $rounding = new CommercialRounding();
        $result   = $this->calculator->percentage($money, '10', $rounding);

        self::assertSame(1000, $result->minorUnits()); // 10.00 EUR = 10%
    }

    public function testApplyCoefficient(): void
    {
        $money    = Money::fromMinorUnits(1000, $this->eur); // 10.00 EUR
        $rounding = new CommercialRounding();
        $result   = $this->calculator->applyCoefficient($money, '1.07', $rounding);

        self::assertSame(1070, $result->minorUnits()); // 10.70 EUR
    }

    public function testAllocateDistributesAmountExactly(): void
    {
        $money    = Money::fromMinorUnits(100, $this->eur);
        $rounding = new CommercialRounding();
        $parts    = $this->calculator->allocate($money, [1, 1, 1], $rounding);

        self::assertCount(3, $parts);

        $total = array_sum(array_map(static fn (Money $m) => $m->minorUnits(), $parts));
        self::assertSame(100, $total);
    }

    public function testAllocateZeroMoney(): void
    {
        $money    = Money::zero($this->eur);
        $rounding = new CommercialRounding();
        $parts    = $this->calculator->allocate($money, [1, 1, 1], $rounding);

        self::assertCount(3, $parts);

        foreach ($parts as $part) {
            self::assertTrue($part->isZero());
        }
    }

    public function testAllocateThrowsOnEmptyRatios(): void
    {
        $money    = Money::fromMinorUnits(100, $this->eur);
        $rounding = new CommercialRounding();

        $this->expectException(\App\System\Money\Domain\Exception\AllocationException::class);

        $this->calculator->allocate($money, [], $rounding);
    }

    public function testAllocateThrowsOnAllZeroRatios(): void
    {
        $money    = Money::fromMinorUnits(100, $this->eur);
        $rounding = new CommercialRounding();

        $this->expectException(\App\System\Money\Domain\Exception\AllocationException::class);

        $this->calculator->allocate($money, [0, 0, 0], $rounding);
    }

    public function testAbs(): void
    {
        $money  = Money::fromMinorUnits(-500, $this->eur);
        $result = $this->calculator->abs($money);

        self::assertSame(500, $result->minorUnits());
    }

    public function testNeg(): void
    {
        $money  = Money::fromMinorUnits(500, $this->eur);
        $result = $this->calculator->neg($money);

        self::assertSame(-500, $result->minorUnits());
    }
}
