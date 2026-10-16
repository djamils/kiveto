<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure\Adapter\Animal;

use App\Context\Animal\Application\Command\CreateAnimal\CreateAnimal;
use App\Context\Client\Application\Command\CreateClient\NewAnimalDto;
use App\Context\Client\Infrastructure\Adapter\Animal\CommandBusClientAnimalRegistry;
use App\Shared\Application\Bus\CommandBusInterface;
use PHPUnit\Framework\TestCase;

final class CommandBusClientAnimalRegistryTest extends TestCase
{
    public function testAsksTheAnimalContextToOpenTheRecordOfTheNewClientsAnimal(): void
    {
        $bus = $this->createMock(CommandBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (object $command): bool => $command instanceof CreateAnimal
                && 'clinic' === $command->clinicId
                && 'Caramel' === $command->name
                && 'nac' === $command->species
                && 'female' === $command->sex
                && 'neutered' === $command->reproductiveStatus
                && false === $command->isMixedBreed
                && 'Lapin bélier nain' === $command->breedName
                && '2022-03-14' === $command->birthDate
                && '250269800771002' === $command->microchipNumber
                && 'alive' === $command->lifeStatus
                && 'owner' === $command->primaryOwnerClientId
                && [] === $command->secondaryOwnerClientIds))
            ->willReturn('animal')
        ;

        $id = (new CommandBusClientAnimalRegistry($bus))->register('clinic', 'owner', new NewAnimalDto(
            name: 'Caramel',
            species: 'nac',
            sex: 'female',
            reproductiveStatus: 'neutered',
            isMixedBreed: false,
            breedName: 'Lapin bélier nain',
            birthDate: '2022-03-14',
            microchipNumber: '250269800771002',
        ));

        self::assertSame('animal', $id);
    }
}
