<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Money\Domain\ValueObject;

use App\System\Money\Domain\ValueObject\CurrencySymbol;
use PHPUnit\Framework\TestCase;

final class CurrencySymbolTest extends TestCase
{
    public function testFromStringCreatesSymbol(): void
    {
        $symbol = CurrencySymbol::fromString('€');

        self::assertSame('€', $symbol->toString());
    }

    public function testFromStringThrowsOnEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CurrencySymbol::fromString('');
    }

    public function testFromStringThrowsOnTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // 9 characters exceeds the 8-character limit
        CurrencySymbol::fromString('123456789');
    }

    public function testFromStringTrimsWhitespace(): void
    {
        $symbol = CurrencySymbol::fromString('  €  ');

        self::assertSame('€', $symbol->toString());
    }

    public function testEquals(): void
    {
        $a = CurrencySymbol::fromString('€');
        $b = CurrencySymbol::fromString('€');
        $c = CurrencySymbol::fromString('$');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
