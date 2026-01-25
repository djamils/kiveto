<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Application\Command;

use App\Animal\Application\Command\UpdateAnimalIdentity\UpdateAnimalIdentity;
use App\Shared\Application\Bus\CommandInterface;
use PHPUnit\Framework\TestCase;

final class UpdateAnimalIdentityTest extends TestCase
{
    public function testConstruct(): void
    {
        $command = new UpdateAnimalIdentity(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            animalId: '01234567-89ab-cdef-0123-456789abcdef',
            name: 'Rex Updated',
            species: 'cat',
            sex: 'female',
            reproductiveStatus: 'neutered',
            isMixedBreed: true,
            breedName: null,
            birthDate: '2021-05-15',
            color: 'Black',
            photoUrl: 'https://example.com/updated.jpg',
            microchipNumber: '987654321',
            tattooNumber: null,
            passportNumber: 'PASS999',
            registryType: 'loof',
            registryNumber: 'LOOF999',
            sireNumber: null,
            auxiliaryContactFirstName: 'Jane',
            auxiliaryContactLastName: 'Smith',
            auxiliaryContactPhoneNumber: '+33698765432',
        );

        self::assertInstanceOf(CommandInterface::class, $command);
        self::assertSame('12345678-9abc-def0-1234-56789abcdef0', $command->clinicId);
        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $command->animalId);
        self::assertSame('Rex Updated', $command->name);
        self::assertSame('cat', $command->species);
        self::assertSame('female', $command->sex);
        self::assertSame('neutered', $command->reproductiveStatus);
        self::assertTrue($command->isMixedBreed);
        self::assertNull($command->breedName);
        self::assertSame('2021-05-15', $command->birthDate);
        self::assertSame('Black', $command->color);
        self::assertSame('https://example.com/updated.jpg', $command->photoUrl);
        self::assertSame('987654321', $command->microchipNumber);
        self::assertNull($command->tattooNumber);
        self::assertSame('PASS999', $command->passportNumber);
        self::assertSame('loof', $command->registryType);
        self::assertSame('LOOF999', $command->registryNumber);
        self::assertNull($command->sireNumber);
        self::assertSame('Jane', $command->auxiliaryContactFirstName);
        self::assertSame('Smith', $command->auxiliaryContactLastName);
        self::assertSame('+33698765432', $command->auxiliaryContactPhoneNumber);
    }
}
