<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Domain\StockItem;

use App\Context\Inventory\Domain\StockItem\Exception\NegativeCoefficientException;
use App\Context\Inventory\Domain\StockItem\Exception\NegativeQuantityException;
use App\Context\Inventory\Domain\StockItem\Exception\UnitMismatchException;
use App\Context\Inventory\Domain\StockItem\ValueObject\Quantity;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use PHPUnit\Framework\TestCase;

final class QuantityTest extends TestCase
{
    private UnitOfMeasure $kg;
    private UnitOfMeasure $unit;

    protected function setUp(): void
    {
        $this->kg   = UnitOfMeasure::fromString('KG');
        $this->unit = UnitOfMeasure::fromString('UNIT');
    }

    public function testOfCreatesQuantityWithValidAmount(): void
    {
        $qty = Quantity::of('12.500000', $this->kg);

        self::assertSame('12.500000', $qty->amount());
        self::assertTrue($this->kg->equals($qty->unit()));
    }

    public function testOfThrowsOnNegativeAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Quantity::of('-5', $this->kg);
    }

    public function testOfThrowsOnCommaFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Quantity::of('5,5', $this->kg);
    }

    public function testOfThrowsOnAlphaString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Quantity::of('abc', $this->kg);
    }

    public function testZeroCreatesZeroQuantity(): void
    {
        $qty = Quantity::zero($this->kg);

        self::assertSame('0.000000', $qty->amount());
        self::assertTrue($qty->isZero());
    }

    public function testAddReturnsSumWithSameUnit(): void
    {
        $a   = Quantity::of('3.000000', $this->kg);
        $b   = Quantity::of('2.500000', $this->kg);
        $sum = $a->add($b);

        self::assertSame('5.500000', $sum->amount());
    }

    public function testAddThrowsOnUnitMismatch(): void
    {
        $this->expectException(UnitMismatchException::class);

        $a = Quantity::of('3.000000', $this->kg);
        $b = Quantity::of('2.000000', $this->unit);

        $a->add($b);
    }

    public function testSubtractReturnsDifference(): void
    {
        $a      = Quantity::of('10.000000', $this->kg);
        $b      = Quantity::of('3.000000', $this->kg);
        $result = $a->subtract($b);

        self::assertSame('7.000000', $result->amount());
    }

    public function testSubtractThrowsNegativeQuantityException(): void
    {
        $this->expectException(NegativeQuantityException::class);

        $a = Quantity::of('3.000000', $this->kg);
        $b = Quantity::of('10.000000', $this->kg);

        $a->subtract($b);
    }

    public function testSubtractThrowsOnUnitMismatch(): void
    {
        $this->expectException(UnitMismatchException::class);

        $a = Quantity::of('10.000000', $this->kg);
        $b = Quantity::of('3.000000', $this->unit);

        $a->subtract($b);
    }

    public function testMultiplyByCoefficientReturnResult(): void
    {
        $qty    = Quantity::of('4.000000', $this->kg);
        $result = $qty->multiplyByCoefficient('2.5');

        self::assertSame('10.000000', $result->amount());
    }

    public function testMultiplyByCoefficientThrowsNegativeCoefficientExceptionOnZero(): void
    {
        $this->expectException(NegativeCoefficientException::class);

        $qty = Quantity::of('4.000000', $this->kg);
        $qty->multiplyByCoefficient('0');
    }

    public function testMultiplyByCoefficientThrowsNegativeCoefficientExceptionOnNegative(): void
    {
        $this->expectException(NegativeCoefficientException::class);

        $qty = Quantity::of('4.000000', $this->kg);
        $qty->multiplyByCoefficient('-1');
    }

    public function testIsZeroReturnsTrueForZero(): void
    {
        $qty = Quantity::zero($this->kg);

        self::assertTrue($qty->isZero());
        self::assertFalse(Quantity::of('0.000001', $this->kg)->isZero());
    }

    public function testIsGreaterThanReturnsTrueWhenExpected(): void
    {
        $bigger  = Quantity::of('10.000000', $this->kg);
        $smaller = Quantity::of('5.000000', $this->kg);

        self::assertTrue($bigger->isGreaterThan($smaller));
        self::assertFalse($smaller->isGreaterThan($bigger));
    }

    public function testIsLessThanReturnsTrueWhenExpected(): void
    {
        $bigger  = Quantity::of('10.000000', $this->kg);
        $smaller = Quantity::of('5.000000', $this->kg);

        self::assertTrue($smaller->isLessThan($bigger));
        self::assertFalse($bigger->isLessThan($smaller));
    }

    public function testEqualsReturnsTrueForSameValues(): void
    {
        $a = Quantity::of('5.000000', $this->kg);
        $b = Quantity::of('5.000000', $this->kg);
        $c = Quantity::of('5.000001', $this->kg);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    public function testToStringFormatsCorrectly(): void
    {
        $qty = Quantity::of('12.500000', $this->kg);

        self::assertSame('12.500000 KG', $qty->toString());
    }

    public function testDecimalPrecisionMaintained(): void
    {
        $qty = Quantity::of('1.123456', $this->kg);

        self::assertSame('1.123456', $qty->amount());
    }
}
