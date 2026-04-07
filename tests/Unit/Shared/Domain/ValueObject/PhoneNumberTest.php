<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\PhoneNumber;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    public function testFromStringWithValidPhoneNumber(): void
    {
        $phone = PhoneNumber::fromString('0123456789');

        $this->assertSame('0123456789', $phone->toString());
    }

    public function testFromStringWithInternationalFormat(): void
    {
        $phone = PhoneNumber::fromString('+33123456789');

        $this->assertSame('+33123456789', $phone->toString());
    }

    public function testFromStringRemovesWhitespace(): void
    {
        $phone = PhoneNumber::fromString('01 23 45 67 89');

        $this->assertSame('0123456789', $phone->toString());
    }

    public function testFromStringTrimsWhitespace(): void
    {
        $phone = PhoneNumber::fromString('  0123456789  ');

        $this->assertSame('0123456789', $phone->toString());
    }

    public function testFromStringWithMultipleSpaces(): void
    {
        $phone = PhoneNumber::fromString('+33  1  23  45  67  89');

        $this->assertSame('+33123456789', $phone->toString());
    }

    public function testFromStringRejectsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Phone number cannot be empty.');

        PhoneNumber::fromString('');
    }

    public function testFromStringRejectsWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Phone number cannot be empty.');

        PhoneNumber::fromString('   ');
    }

    public function testFromStringRejectsTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid phone number: "12345".');

        PhoneNumber::fromString('12345');
    }

    public function testFromStringRejectsTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid phone number: "123456789012345678901".');

        PhoneNumber::fromString('123456789012345678901');
    }

    public function testFromStringRejectsLetters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid phone number: "01234ABCDE".');

        PhoneNumber::fromString('01234ABCDE');
    }

    public function testFromStringRejectsSpecialCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid phone number: "0123-456-789".');

        PhoneNumber::fromString('0123-456-789');
    }

    public function testFromStringRejectsParentheses(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid phone number: "(01) 23 45 67 89".');

        PhoneNumber::fromString('(01) 23 45 67 89');
    }

    public function testEquals(): void
    {
        $phone1 = PhoneNumber::fromString('0123456789');
        $phone2 = PhoneNumber::fromString('0123456789');
        $phone3 = PhoneNumber::fromString('9876543210');

        $this->assertTrue($phone1->equals($phone2));
        $this->assertFalse($phone1->equals($phone3));
    }

    public function testEqualsWithNormalizedSpaces(): void
    {
        $phone1 = PhoneNumber::fromString('01 23 45 67 89');
        $phone2 = PhoneNumber::fromString('0123456789');

        $this->assertTrue($phone1->equals($phone2));
    }

    public function testAcceptsMinimumLength(): void
    {
        $phone = PhoneNumber::fromString('123456');

        $this->assertSame('123456', $phone->toString());
    }

    public function testAcceptsMaximumLength(): void
    {
        $phone = PhoneNumber::fromString('12345678901234567890');

        $this->assertSame('12345678901234567890', $phone->toString());
    }
}
