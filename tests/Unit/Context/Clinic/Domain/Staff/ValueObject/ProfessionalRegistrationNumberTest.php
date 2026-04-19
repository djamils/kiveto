<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Staff\ValueObject;

use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalRegistrationNumber;
use PHPUnit\Framework\TestCase;

final class ProfessionalRegistrationNumberTest extends TestCase
{
    public function testFromStringWithValidNumber(): void
    {
        $number = ProfessionalRegistrationNumber::fromString('FR-12345');

        self::assertSame('FR-12345', $number->toString());
    }

    public function testFromStringTrimsWhitespace(): void
    {
        $number = ProfessionalRegistrationNumber::fromString('  FR-12345  ');

        self::assertSame('FR-12345', $number->toString());
    }

    public function testFromStringRejectsEmptyValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Professional registration number cannot be empty.');

        ProfessionalRegistrationNumber::fromString('');
    }

    public function testFromStringRejectsWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Professional registration number cannot be empty.');

        ProfessionalRegistrationNumber::fromString('   ');
    }

    public function testFromStringRejectsValueExceeding32Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Professional registration number cannot exceed 32 characters');

        ProfessionalRegistrationNumber::fromString(str_repeat('a', 33));
    }

    public function testFromStringAcceptsExactly32Characters(): void
    {
        $number = ProfessionalRegistrationNumber::fromString(str_repeat('a', 32));

        self::assertSame(32, mb_strlen($number->toString()));
    }

    public function testEquals(): void
    {
        $a = ProfessionalRegistrationNumber::fromString('FR-12345');
        $b = ProfessionalRegistrationNumber::fromString('FR-12345');
        $c = ProfessionalRegistrationNumber::fromString('UK-99999');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
