<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Staff\ValueObject;

use App\Context\Clinic\Domain\Staff\ValueObject\HexColor;
use PHPUnit\Framework\TestCase;

final class HexColorTest extends TestCase
{
    public function testFromStringWithValidLowercaseColor(): void
    {
        $color = HexColor::fromString('#1a2b3c');

        self::assertSame('#1a2b3c', $color->toString());
    }

    public function testFromStringNormalizesToLowercase(): void
    {
        $color = HexColor::fromString('#1A2B3C');

        self::assertSame('#1a2b3c', $color->toString());
    }

    public function testFromStringRejectsInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid hex color');

        HexColor::fromString('1a2b3c');
    }

    public function testFromStringRejectsShortFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        HexColor::fromString('#1a2b3');
    }

    public function testFromStringRejectsNonHexCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        HexColor::fromString('#gggggg');
    }

    public function testFromStringRejectsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        HexColor::fromString('');
    }

    public function testEquals(): void
    {
        $a = HexColor::fromString('#ff0000');
        $b = HexColor::fromString('#FF0000');
        $c = HexColor::fromString('#00ff00');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
