<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Application\Command;

use App\Animal\Application\Command\CreateAnimal\CreateAnimal;
use App\Shared\Application\Bus\CommandInterface;
use PHPUnit\Framework\TestCase;

final class CreateAnimalTest extends TestCase
{
    public function testConstruct(): void
    {
        $command = new CreateAnimal(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            name: 'Rex',
            species: 'dog',
            sex: 'male',
            reproductiveStatus: 'intact',
            isMixedBreed: false,
            breedName: 'Labrador',
            birthDate: '2020-01-01',
            color: 'Golden',
            photoUrl: 'https://example.com/photo.jpg',
            microchipNumber: '123456789',
            tattooNumber: 'TAT123',
            passportNumber: 'PASS123',
            registryType: 'lof',
            registryNumber: 'LOF123',
            sireNumber: 'SIRE123',
            lifeStatus: 'alive',
            deceasedAt: null,
            missingSince: null,
            transferStatus: 'none',
            soldAt: null,
            givenAt: null,
            auxiliaryContactFirstName: 'John',
            auxiliaryContactLastName: 'Doe',
            auxiliaryContactPhoneNumber: '+33612345678',
            primaryOwnerClientId: 'client-123',
            secondaryOwnerClientIds: ['client-456', 'client-789'],
        );

        self::assertInstanceOf(CommandInterface::class, $command);
        self::assertSame('12345678-9abc-def0-1234-56789abcdef0', $command->clinicId);
        self::assertSame('Rex', $command->name);
        self::assertSame('dog', $command->species);
        self::assertSame('male', $command->sex);
        self::assertSame('intact', $command->reproductiveStatus);
        self::assertFalse($command->isMixedBreed);
        self::assertSame('Labrador', $command->breedName);
        self::assertSame('2020-01-01', $command->birthDate);
        self::assertSame('Golden', $command->color);
        self::assertSame('https://example.com/photo.jpg', $command->photoUrl);
        self::assertSame('123456789', $command->microchipNumber);
        self::assertSame('TAT123', $command->tattooNumber);
        self::assertSame('PASS123', $command->passportNumber);
        self::assertSame('lof', $command->registryType);
        self::assertSame('LOF123', $command->registryNumber);
        self::assertSame('SIRE123', $command->sireNumber);
        self::assertSame('alive', $command->lifeStatus);
        self::assertNull($command->deceasedAt);
        self::assertNull($command->missingSince);
        self::assertSame('none', $command->transferStatus);
        self::assertNull($command->soldAt);
        self::assertNull($command->givenAt);
        self::assertSame('John', $command->auxiliaryContactFirstName);
        self::assertSame('Doe', $command->auxiliaryContactLastName);
        self::assertSame('+33612345678', $command->auxiliaryContactPhoneNumber);
        self::assertSame('client-123', $command->primaryOwnerClientId);
        self::assertSame(['client-456', 'client-789'], $command->secondaryOwnerClientIds);
    }
}
