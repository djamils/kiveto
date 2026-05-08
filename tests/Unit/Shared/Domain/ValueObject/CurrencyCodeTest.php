<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\CurrencyCode;
use PHPUnit\Framework\TestCase;

final class CurrencyCodeTest extends TestCase
{
    public function testFromStringAcceptsValidCode(): void
    {
        self::assertSame('EUR', CurrencyCode::fromString('EUR')->toString());
        self::assertSame('USD', CurrencyCode::fromString('USD')->toString());
    }

    public function testFromStringRejectsLowercase(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CurrencyCode::fromString('eur');
    }

    public function testFromStringRejectsTwoLetterCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CurrencyCode::fromString('EU');
    }

    public function testFromStringRejectsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CurrencyCode::fromString('');
    }

    public function testEqualsSameValue(): void
    {
        self::assertTrue(CurrencyCode::fromString('EUR')->equals(CurrencyCode::fromString('EUR')));
    }

    public function testEqualsDifferentValue(): void
    {
        self::assertFalse(CurrencyCode::fromString('EUR')->equals(CurrencyCode::fromString('USD')));
    }
}
