<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\CountryCode;
use PHPUnit\Framework\TestCase;

final class CountryCodeTest extends TestCase
{
    public function testFromStringAcceptsValidCode(): void
    {
        self::assertSame('FR', CountryCode::fromString('FR')->toString());
        self::assertSame('DE', CountryCode::fromString('DE')->toString());
    }

    public function testFromStringRejectsLowercase(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CountryCode::fromString('fr');
    }

    public function testFromStringRejectsThreeLetterCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CountryCode::fromString('FRA');
    }

    public function testFromStringRejectsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CountryCode::fromString('');
    }

    public function testEqualsSameValue(): void
    {
        self::assertTrue(CountryCode::fromString('FR')->equals(CountryCode::fromString('FR')));
    }

    public function testEqualsDifferentValue(): void
    {
        self::assertFalse(CountryCode::fromString('FR')->equals(CountryCode::fromString('DE')));
    }
}
