<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\PostalAddress;
use PHPUnit\Framework\TestCase;

final class PostalAddressTest extends TestCase
{
    public function testCreateWithAllFields(): void
    {
        $address = PostalAddress::create(
            streetLine1: '123 Main Street',
            city: 'Paris',
            countryCode: 'FR',
            streetLine2: 'Apt 4B',
            postalCode: '75001',
            region: 'Île-de-France',
        );

        $this->assertSame('123 Main Street', $address->streetLine1);
        $this->assertSame('Apt 4B', $address->streetLine2);
        $this->assertSame('75001', $address->postalCode);
        $this->assertSame('Paris', $address->city);
        $this->assertSame('Île-de-France', $address->region);
        $this->assertSame('FR', $address->countryCode);
    }

    public function testCreateWithOnlyRequiredFields(): void
    {
        $address = PostalAddress::create(
            streetLine1: '123 Main Street',
            city: 'Paris',
            countryCode: 'FR',
        );

        $this->assertSame('123 Main Street', $address->streetLine1);
        $this->assertNull($address->streetLine2);
        $this->assertNull($address->postalCode);
        $this->assertSame('Paris', $address->city);
        $this->assertNull($address->region);
        $this->assertSame('FR', $address->countryCode);
    }

    public function testCreateTrimsWhitespace(): void
    {
        $address = PostalAddress::create(
            streetLine1: '  123 Main Street  ',
            city: '  Paris  ',
            countryCode: '  fr  ',
            streetLine2: '  Apt 4B  ',
            postalCode: '  75001  ',
            region: '  Île-de-France  ',
        );

        $this->assertSame('123 Main Street', $address->streetLine1);
        $this->assertSame('Apt 4B', $address->streetLine2);
        $this->assertSame('75001', $address->postalCode);
        $this->assertSame('Paris', $address->city);
        $this->assertSame('Île-de-France', $address->region);
    }

    public function testCreateNormalizesCountryCodeToUpperCase(): void
    {
        $address = PostalAddress::create(
            streetLine1: '123 Main Street',
            city: 'Paris',
            countryCode: 'fr',
        );

        $this->assertSame('FR', $address->countryCode);
    }

    public function testCreateConvertsEmptyOptionalFieldsToNull(): void
    {
        $address = PostalAddress::create(
            streetLine1: '123 Main Street',
            city: 'Paris',
            countryCode: 'FR',
            streetLine2: '',
            postalCode: '   ',
            region: '',
        );

        $this->assertNull($address->streetLine2);
        $this->assertNull($address->postalCode);
        $this->assertNull($address->region);
    }

    public function testCreateRejectsEmptyStreetLine1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Street line 1 cannot be empty.');

        PostalAddress::create(
            streetLine1: '',
            city: 'Paris',
            countryCode: 'FR',
        );
    }

    public function testCreateRejectsWhitespaceOnlyStreetLine1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Street line 1 cannot be empty.');

        PostalAddress::create(
            streetLine1: '   ',
            city: 'Paris',
            countryCode: 'FR',
        );
    }

    public function testCreateRejectsEmptyCity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('City cannot be empty.');

        PostalAddress::create(
            streetLine1: '123 Main Street',
            city: '',
            countryCode: 'FR',
        );
    }

    public function testCreateRejectsWhitespaceOnlyCity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('City cannot be empty.');

        PostalAddress::create(
            streetLine1: '123 Main Street',
            city: '   ',
            countryCode: 'FR',
        );
    }

    public function testCreateRejectsInvalidCountryCodeFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Country code must be 2 uppercase letters (ISO 3166-1 alpha-2), got: "FRA".');

        PostalAddress::create(
            streetLine1: '123 Main Street',
            city: 'Paris',
            countryCode: 'FRA',
        );
    }

    public function testCreateRejectsCountryCodeWithNumbers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Country code must be 2 uppercase letters (ISO 3166-1 alpha-2), got: "F1".');

        PostalAddress::create(
            streetLine1: '123 Main Street',
            city: 'Paris',
            countryCode: 'F1',
        );
    }

    public function testCreateRejectsCountryCodeTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Country code must be 2 uppercase letters (ISO 3166-1 alpha-2), got: "F".');

        PostalAddress::create(
            streetLine1: '123 Main Street',
            city: 'Paris',
            countryCode: 'F',
        );
    }

    public function testEquals(): void
    {
        $address1 = PostalAddress::create(
            streetLine1: '123 Main Street',
            city: 'Paris',
            countryCode: 'FR',
            streetLine2: 'Apt 4B',
            postalCode: '75001',
            region: 'Île-de-France',
        );

        $address2 = PostalAddress::create(
            streetLine1: '123 Main Street',
            city: 'Paris',
            countryCode: 'FR',
            streetLine2: 'Apt 4B',
            postalCode: '75001',
            region: 'Île-de-France',
        );

        $address3 = PostalAddress::create(
            streetLine1: '456 Other Street',
            city: 'Paris',
            countryCode: 'FR',
        );

        $this->assertTrue($address1->equals($address2));
        $this->assertFalse($address1->equals($address3));
    }

    public function testEqualsWithDifferentOptionalFields(): void
    {
        $address1 = PostalAddress::create(
            streetLine1: '123 Main Street',
            city: 'Paris',
            countryCode: 'FR',
            postalCode: '75001',
        );

        $address2 = PostalAddress::create(
            streetLine1: '123 Main Street',
            city: 'Paris',
            countryCode: 'FR',
            postalCode: '75002',
        );

        $this->assertFalse($address1->equals($address2));
    }

    public function testEqualsWithNullOptionalFields(): void
    {
        $address1 = PostalAddress::create(
            streetLine1: '123 Main Street',
            city: 'Paris',
            countryCode: 'FR',
        );

        $address2 = PostalAddress::create(
            streetLine1: '123 Main Street',
            city: 'Paris',
            countryCode: 'FR',
            streetLine2: null,
            postalCode: null,
            region: null,
        );

        $this->assertTrue($address1->equals($address2));
    }
}
