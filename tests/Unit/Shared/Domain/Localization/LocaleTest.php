<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Localization;

use App\Shared\Domain\Localization\Locale;
use PHPUnit\Framework\TestCase;

final class LocaleTest extends TestCase
{
    public function testFromStringWithValidBcp47(): void
    {
        $locale = Locale::fromString('fr-FR');

        $this->assertSame('fr-FR', $locale->toString());
        $this->assertSame('fr-FR', (string) $locale);
    }

    public function testFromStringWithValidEnglishLocale(): void
    {
        $locale = Locale::fromString('en-US');

        $this->assertSame('en-US', $locale->toString());
    }

    public function testFromStringTrimsWhitespace(): void
    {
        $locale = Locale::fromString('  fr-FR  ');

        $this->assertSame('fr-FR', $locale->toString());
    }

    public function testFromStringRejectsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Locale cannot be empty.');

        Locale::fromString('');
    }

    public function testFromStringRejectsWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Locale cannot be empty.');

        Locale::fromString('   ');
    }

    public function testFromStringRejectsShortCodeOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid locale: "fr".');

        Locale::fromString('fr');
    }

    public function testFromStringRejectsUnderscore(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid locale: "fr_FR".');

        Locale::fromString('fr_FR');
    }

    public function testFromStringRejectsWrongCase(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid locale: "FR-fr".');

        Locale::fromString('FR-fr');
    }

    public function testFromStringRejectsAllLowerCase(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid locale: "fr-fr".');

        Locale::fromString('fr-fr');
    }

    public function testFromStringRejectsInvalidLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid locale: "f-FR".');

        Locale::fromString('f-FR');
    }

    public function testFromStringRejectsTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid locale: "fra-FR".');

        Locale::fromString('fra-FR');
    }

    public function testEquals(): void
    {
        $locale1 = Locale::fromString('fr-FR');
        $locale2 = Locale::fromString('fr-FR');
        $locale3 = Locale::fromString('en-US');

        $this->assertTrue($locale1->equals($locale2));
        $this->assertFalse($locale1->equals($locale3));
    }
}
