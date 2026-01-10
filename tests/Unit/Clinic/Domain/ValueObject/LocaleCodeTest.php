<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Domain\ValueObject;

use App\Clinic\Domain\ValueObject\LocaleCode;
use PHPUnit\Framework\TestCase;

final class LocaleCodeTest extends TestCase
{
    public function testFromStringWithShortLocale(): void
    {
        $locale = LocaleCode::fromString('fr');

        self::assertSame('fr', $locale->toString());
    }

    public function testFromStringWithFullLocale(): void
    {
        $locale = LocaleCode::fromString('fr_FR');

        self::assertSame('fr_FR', $locale->toString());
    }

    public function testFromStringRejectsEmptyValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Locale code cannot be empty');

        LocaleCode::fromString('');
    }

    public function testFromStringRejectsInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid locale code format');

        LocaleCode::fromString('french');
    }

    public function testEquals(): void
    {
        $localeA = LocaleCode::fromString('fr');
        $localeB = LocaleCode::fromString('fr');
        $localeC = LocaleCode::fromString('en');

        self::assertTrue($localeA->equals($localeB));
        self::assertFalse($localeA->equals($localeC));
    }
}
