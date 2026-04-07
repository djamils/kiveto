<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Domain\ValueObject;

use App\Client\Domain\ValueObject\ContactLabel;
use App\Client\Domain\ValueObject\ContactMethod;
use App\Client\Domain\ValueObject\ContactMethodType;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use PHPUnit\Framework\TestCase;

final class ContactMethodTest extends TestCase
{
    public function testCreatesPhoneContactMethod(): void
    {
        $phoneNumber = PhoneNumber::fromString('+33612345678');
        $contact     = ContactMethod::phone($phoneNumber, ContactLabel::MOBILE, true);

        self::assertSame(ContactMethodType::PHONE, $contact->type);
        self::assertSame(ContactLabel::MOBILE, $contact->label);
        self::assertSame('+33612345678', $contact->value);
        self::assertTrue($contact->isPrimary);
    }

    public function testCreatesEmailContactMethod(): void
    {
        $email   = EmailAddress::fromString('john@example.com');
        $contact = ContactMethod::email($email, ContactLabel::WORK, false);

        self::assertSame(ContactMethodType::EMAIL, $contact->type);
        self::assertSame(ContactLabel::WORK, $contact->label);
        self::assertSame('john@example.com', $contact->value);
        self::assertFalse($contact->isPrimary);
    }

    public function testIsPhone(): void
    {
        $phoneNumber = PhoneNumber::fromString('+33612345678');
        $phone       = ContactMethod::phone($phoneNumber, ContactLabel::MOBILE);

        self::assertTrue($phone->isPhone());
        self::assertFalse($phone->isEmail());
    }

    public function testIsEmail(): void
    {
        $email        = EmailAddress::fromString('john@example.com');
        $emailContact = ContactMethod::email($email, ContactLabel::WORK);

        self::assertTrue($emailContact->isEmail());
        self::assertFalse($emailContact->isPhone());
    }

    public function testEqualsReturnsTrueForSameTypeAndValue(): void
    {
        $email1 = ContactMethod::email(
            EmailAddress::fromString('john@example.com'),
            ContactLabel::WORK,
            true
        );
        $email2 = ContactMethod::email(
            EmailAddress::fromString('john@example.com'),
            ContactLabel::HOME,
            false
        );

        self::assertTrue($email1->equals($email2));
    }

    public function testEqualsReturnsFalseForDifferentValues(): void
    {
        $email1 = ContactMethod::email(
            EmailAddress::fromString('john@example.com'),
            ContactLabel::WORK
        );
        $email2 = ContactMethod::email(
            EmailAddress::fromString('jane@example.com'),
            ContactLabel::WORK
        );

        self::assertFalse($email1->equals($email2));
    }

    public function testEqualsReturnsFalseForDifferentTypes(): void
    {
        $phone = ContactMethod::phone(
            PhoneNumber::fromString('+33612345678'),
            ContactLabel::MOBILE
        );
        $email = ContactMethod::email(
            EmailAddress::fromString('john@example.com'),
            ContactLabel::WORK
        );

        self::assertFalse($phone->equals($email));
    }

    public function testPhoneContactMethodDefaultsToNotPrimary(): void
    {
        $phoneNumber = PhoneNumber::fromString('+33612345678');
        $contact     = ContactMethod::phone($phoneNumber, ContactLabel::MOBILE);

        self::assertFalse($contact->isPrimary);
    }

    public function testEmailContactMethodDefaultsToNotPrimary(): void
    {
        $email   = EmailAddress::fromString('john@example.com');
        $contact = ContactMethod::email($email, ContactLabel::WORK);

        self::assertFalse($contact->isPrimary);
    }
}
