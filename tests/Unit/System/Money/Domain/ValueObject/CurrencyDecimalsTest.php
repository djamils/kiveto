<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Money\Domain\ValueObject;

use App\System\Money\Domain\ValueObject\CurrencyDecimals;
use PHPUnit\Framework\TestCase;

final class CurrencyDecimalsTest extends TestCase
{
    public function testOfCreatesValidDecimals(): void
    {
        foreach ([0, 1, 2, 3, 4] as $value) {
            $decimals = CurrencyDecimals::of($value);
            self::assertSame($value, $decimals->value());
        }
    }

    public function testOfThrowsOnNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CurrencyDecimals::of(-1);
    }

    public function testOfThrowsOnAboveFour(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CurrencyDecimals::of(5);
    }

    public function testEquals(): void
    {
        $a = CurrencyDecimals::of(2);
        $b = CurrencyDecimals::of(2);
        $c = CurrencyDecimals::of(3);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    public function testValue(): void
    {
        $decimals = CurrencyDecimals::of(2);

        self::assertSame(2, $decimals->value());
    }
}
