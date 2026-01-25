<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Application\Command\UpdateClientPostalAddress;

use App\Client\Application\Command\UpdateClientPostalAddress\PostalAddressDto;
use App\Client\Application\Command\UpdateClientPostalAddress\UpdateClientPostalAddress;
use PHPUnit\Framework\TestCase;

final class UpdateClientPostalAddressTest extends TestCase
{
    public function testConstructorSetsPropertiesWithAddress(): void
    {
        $postalAddress = new PostalAddressDto(
            streetLine1: '123 Main St',
            city: 'Paris',
            countryCode: 'FR',
            streetLine2: 'Apt 4B',
            postalCode: '75001',
            region: 'Île-de-France'
        );

        $command = new UpdateClientPostalAddress(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            clientId: '01234567-89ab-cdef-0123-456789abcdef',
            postalAddress: $postalAddress
        );

        self::assertSame('12345678-9abc-def0-1234-56789abcdef0', $command->clinicId);
        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $command->clientId);
        self::assertSame($postalAddress, $command->postalAddress);
    }

    public function testConstructorDefaultsPostalAddressToNull(): void
    {
        $command = new UpdateClientPostalAddress(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            clientId: '01234567-89ab-cdef-0123-456789abcdef'
        );

        self::assertNull($command->postalAddress);
    }
}
