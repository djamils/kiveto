<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Application\Query\GetClientById;

use App\Client\Application\Query\GetClientById\PostalAddressDto;
use PHPUnit\Framework\TestCase;

final class PostalAddressDtoTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $dto = new PostalAddressDto(
            streetLine1: '123 Main St',
            city: 'Paris',
            countryCode: 'FR',
            streetLine2: 'Apt 4B',
            postalCode: '75001',
            region: 'Île-de-France'
        );

        self::assertSame('123 Main St', $dto->streetLine1);
        self::assertSame('Paris', $dto->city);
        self::assertSame('FR', $dto->countryCode);
        self::assertSame('Apt 4B', $dto->streetLine2);
        self::assertSame('75001', $dto->postalCode);
        self::assertSame('Île-de-France', $dto->region);
    }

    public function testConstructorDefaultsOptionalFieldsToNull(): void
    {
        $dto = new PostalAddressDto(
            streetLine1: '123 Main St',
            city: 'Paris',
            countryCode: 'FR'
        );

        self::assertNull($dto->streetLine2);
        self::assertNull($dto->postalCode);
        self::assertNull($dto->region);
    }
}
