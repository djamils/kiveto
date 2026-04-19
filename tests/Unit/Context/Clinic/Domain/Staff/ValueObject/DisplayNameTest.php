<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Staff\ValueObject;

use App\Context\Clinic\Domain\Staff\ValueObject\DisplayName;
use PHPUnit\Framework\TestCase;

final class DisplayNameTest extends TestCase
{
    public function testFromStringWithValidName(): void
    {
        $name = DisplayName::fromString('Dr. Sophie Rousseau');

        self::assertSame('Dr. Sophie Rousseau', $name->toString());
        self::assertSame('Dr. Sophie Rousseau', (string) $name);
    }

    public function testFromStringTrimsWhitespace(): void
    {
        $name = DisplayName::fromString('  Jean Dupont  ');

        self::assertSame('Jean Dupont', $name->toString());
    }

    public function testFromStringRejectsEmptyValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Display name cannot be empty.');

        DisplayName::fromString('');
    }

    public function testFromStringRejectsWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Display name cannot be empty.');

        DisplayName::fromString('   ');
    }

    public function testFromStringRejectsNameExceeding60Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Display name cannot exceed 60 characters');

        DisplayName::fromString(str_repeat('a', 61));
    }

    public function testFromStringAcceptsExactly60Characters(): void
    {
        $name = DisplayName::fromString(str_repeat('a', 60));

        self::assertSame(60, mb_strlen($name->toString()));
    }

    public function testEquals(): void
    {
        $a = DisplayName::fromString('Sophie');
        $b = DisplayName::fromString('Sophie');
        $c = DisplayName::fromString('Jean');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
