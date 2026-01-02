<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Domain\ValueObject;

use App\Translation\Domain\ValueObject\Locale;
use PHPUnit\Framework\TestCase;

final class LocaleTest extends TestCase
{
    public function testNormalizeAndValidate(): void
    {
        self::assertSame('fr_FR', Locale::fromString('fr_fr')->toString());
        self::assertSame('en_GB', Locale::fromString('en_GB')->toString());
    }

    public function testInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Locale::fromString('fr-FR');
    }

    public function testEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Locale::fromString('');
    }

    public function testTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Locale::fromString(str_repeat('a', 20));
    }

    public function testEquals(): void
    {
        $l1 = Locale::fromString('fr_FR');
        $l2 = Locale::fromString('fr_FR');
        $l3 = Locale::fromString('en_GB');

        self::assertTrue($l1->equals($l2));
        self::assertFalse($l1->equals($l3));
    }
}
