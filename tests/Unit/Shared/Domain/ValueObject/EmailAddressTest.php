<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;

final class EmailAddressTest extends TestCase
{
    public function testFromStringWithValidEmail(): void
    {
        $email = EmailAddress::fromString('john.doe@example.com');

        $this->assertSame('john.doe@example.com', $email->toString());
    }

    public function testFromStringNormalizesToLowerCase(): void
    {
        $email = EmailAddress::fromString('JOHN.DOE@EXAMPLE.COM');

        $this->assertSame('john.doe@example.com', $email->toString());
    }

    public function testFromStringTrimWhitespace(): void
    {
        $email = EmailAddress::fromString('  john.doe@example.com  ');

        $this->assertSame('john.doe@example.com', $email->toString());
    }

    public function testFromStringRejectsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email address cannot be empty.');

        EmailAddress::fromString('');
    }

    public function testFromStringRejectsWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email address cannot be empty.');

        EmailAddress::fromString('   ');
    }

    public function testFromStringRejectsInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email address: "not-an-email".');

        EmailAddress::fromString('not-an-email');
    }

    public function testFromStringRejectsMissingDomain(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email address: "user@".');

        EmailAddress::fromString('user@');
    }

    public function testFromStringRejectsMissingAt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email address: "userexample.com".');

        EmailAddress::fromString('userexample.com');
    }

    public function testFromStringRejectsMultipleAt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email address: "user@@example.com".');

        EmailAddress::fromString('user@@example.com');
    }

    public function testEquals(): void
    {
        $email1 = EmailAddress::fromString('john@example.com');
        $email2 = EmailAddress::fromString('john@example.com');
        $email3 = EmailAddress::fromString('jane@example.com');

        $this->assertTrue($email1->equals($email2));
        $this->assertFalse($email1->equals($email3));
    }

    public function testEqualsIsCaseInsensitive(): void
    {
        $email1 = EmailAddress::fromString('John@Example.com');
        $email2 = EmailAddress::fromString('john@example.com');

        $this->assertTrue($email1->equals($email2));
    }
}
