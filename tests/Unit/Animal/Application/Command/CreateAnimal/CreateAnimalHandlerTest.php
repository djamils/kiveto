<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Application\Command\CreateAnimal;

use App\Animal\Application\Command\CreateAnimal\CreateAnimal;
use App\Animal\Application\Command\CreateAnimal\CreateAnimalHandler;
use App\Animal\Domain\Animal;
use App\Animal\Domain\Exception\MicrochipAlreadyUsedException;
use App\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Animal\Domain\ValueObject\AnimalId;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class CreateAnimalHandlerTest extends TestCase
{
    public function testHandleCreatesAnimal(): void
    {
        $clinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $animalId = AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $now      = new \DateTimeImmutable('2024-01-01 10:00:00');

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
            secondaryOwnerClientIds: ['client-456'],
        );

        $repository = $this->createMock(AnimalRepositoryInterface::class);
        $eventBus   = $this->createMock(EventBusInterface::class);
        $clock      = $this->createMock(ClockInterface::class);

        $repository->expects(self::once())
            ->method('existsByMicrochip')
            ->with(
                self::callback(fn (ClinicId $id) => $id->equals($clinicId)),
                '123456789'
            )
            ->willReturn(false)
        ;

        $clock->expects(self::once())
            ->method('now')
            ->willReturn($now)
        ;

        $repository->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(Animal::class))
        ;

        $eventBus->expects(self::once())
            ->method('publish')
            ->with([], self::isInstanceOf(DomainEventInterface::class))
        ;

        $handler = new CreateAnimalHandler($repository, $eventBus, $clock);
        $result  = $handler($command);

        self::assertNotEmpty($result);
    }

    public function testHandleThrowsExceptionWhenMicrochipAlreadyUsed(): void
    {
        $command = new CreateAnimal(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            name: 'Rex',
            species: 'dog',
            sex: 'male',
            reproductiveStatus: 'intact',
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            microchipNumber: '123456789',
            tattooNumber: null,
            passportNumber: null,
            registryType: 'none',
            registryNumber: null,
            sireNumber: null,
            lifeStatus: 'alive',
            deceasedAt: null,
            missingSince: null,
            transferStatus: 'none',
            soldAt: null,
            givenAt: null,
            auxiliaryContactFirstName: null,
            auxiliaryContactLastName: null,
            auxiliaryContactPhoneNumber: null,
            primaryOwnerClientId: 'client-123',
            secondaryOwnerClientIds: [],
        );

        $repository = $this->createMock(AnimalRepositoryInterface::class);
        $eventBus   = $this->createStub(EventBusInterface::class);
        $clock      = $this->createStub(ClockInterface::class);

        $repository->expects(self::once())
            ->method('existsByMicrochip')
            ->willReturn(true)
        ;

        $this->expectException(MicrochipAlreadyUsedException::class);

        $handler = new CreateAnimalHandler($repository, $eventBus, $clock);
        $handler($command);
    }
}
