<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\PhoneNumber;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    public function testFromStringWithValidE164(): void
    {
        $phone = PhoneNumber::fromString('+33612345678');

        $this->assertSame('+33612345678', $phone->toString());
    }

    public function testFromStringRemovesWhitespace(): void
    {
        $phone = PhoneNumber::fromString('+33 6 12 34 56 78');

        $this->assertSame('+33612345678', $phone->toString());
    }

    public function testFromStringTrimsWhitespace(): void
    {
        $phone = PhoneNumber::fromString('  +33612345678  ');

        $this->assertSame('+33612345678', $phone->toString());
    }

    public function testFromStringWithMultipleSpaces(): void
    {
        $phone = PhoneNumber::fromString('+33  6  12  34  56  78');

        $this->assertSame('+33612345678', $phone->toString());
    }

    public function testAcceptsMinimumLength(): void
    {
        $phone = PhoneNumber::fromString('+1234567');

        $this->assertSame('+1234567', $phone->toString());
    }

    public function testAcceptsMaximumLength(): void
    {
        $phone = PhoneNumber::fromString('+123456789012345');

        $this->assertSame('+123456789012345', $phone->toString());
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

    public function testFromStringRejectsLocalFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected E.164 format');

        PhoneNumber::fromString('0612345678');
    }

    public function testFromStringRejectsZeroAfterPlus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected E.164 format');

        PhoneNumber::fromString('+0123456789');
    }

    public function testFromStringRejectsTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected E.164 format');

        PhoneNumber::fromString('+123456');
    }

    public function testFromStringRejectsTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected E.164 format');

        PhoneNumber::fromString('+1234567890123456');
    }

    public function testFromStringRejectsLetters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected E.164 format');

        PhoneNumber::fromString('+33ABCDEFGH');
    }

    public function testFromStringRejectsSpecialCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected E.164 format');

        PhoneNumber::fromString('+33-612-345-678');
    }

    public function testFromStringRejectsMissingPlus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected E.164 format');

        PhoneNumber::fromString('33612345678');
    }

    public function testEquals(): void
    {
        $phone1 = PhoneNumber::fromString('+33612345678');
        $phone2 = PhoneNumber::fromString('+33612345678');
        $phone3 = PhoneNumber::fromString('+44791112345');

        $this->assertTrue($phone1->equals($phone2));
        $this->assertFalse($phone1->equals($phone3));
    }

    public function testEqualsWithNormalizedSpaces(): void
    {
        $phone1 = PhoneNumber::fromString('+33 6 12 34 56 78');
        $phone2 = PhoneNumber::fromString('+33612345678');

        $this->assertTrue($phone1->equals($phone2));
    }
}
