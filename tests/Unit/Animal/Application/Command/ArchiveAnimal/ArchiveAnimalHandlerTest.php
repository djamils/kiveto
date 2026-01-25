<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Application\Command\ArchiveAnimal;

use App\Animal\Application\Command\ArchiveAnimal\ArchiveAnimal;
use App\Animal\Application\Command\ArchiveAnimal\ArchiveAnimalHandler;
use App\Animal\Domain\Animal;
use App\Animal\Domain\Exception\AnimalClinicMismatchException;
use App\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Animal\Domain\ValueObject\AnimalId;
use App\Animal\Domain\ValueObject\AnimalStatus;
use App\Animal\Domain\ValueObject\Identification;
use App\Animal\Domain\ValueObject\LifeCycle;
use App\Animal\Domain\ValueObject\ReproductiveStatus;
use App\Animal\Domain\ValueObject\Sex;
use App\Animal\Domain\ValueObject\Species;
use App\Animal\Domain\ValueObject\Transfer;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class ArchiveAnimalHandlerTest extends TestCase
{
    public function testHandleArchivesAnimal(): void
    {
        $clinicId  = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $animalId  = AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $now       = new \DateTimeImmutable('2024-01-02 14:00:00');
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');

        $animal = Animal::reconstituteFromPersistence(
            id: $animalId,
            clinicId: $clinicId,
            name: 'Rex',
            species: Species::DOG,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::INTACT,
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: Identification::createEmpty(),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: null,
            status: AnimalStatus::ACTIVE,
            ownerships: [
                new \App\Animal\Domain\ValueObject\Ownership(
                    clientId: 'client-123',
                    role: \App\Animal\Domain\ValueObject\OwnershipRole::PRIMARY,
                    status: \App\Animal\Domain\ValueObject\OwnershipStatus::ACTIVE,
                    startedAt: $createdAt,
                    endedAt: null
                ),
            ],
            createdAt: $createdAt,
            updatedAt: $createdAt
        );

        $command = new ArchiveAnimal(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            animalId: '01234567-89ab-cdef-0123-456789abcdef'
        );

        $repository = $this->createMock(AnimalRepositoryInterface::class);
        $eventBus   = $this->createMock(EventBusInterface::class);
        $clock      = $this->createMock(ClockInterface::class);

        $repository->expects(self::once())
            ->method('get')
            ->with(
                self::callback(fn (ClinicId $id) => $id->equals($clinicId)),
                self::callback(fn (AnimalId $id) => $id->equals($animalId))
            )
            ->willReturn($animal)
        ;

        $clock->expects(self::once())
            ->method('now')
            ->willReturn($now)
        ;

        $repository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Animal $a): bool {
                return AnimalStatus::ARCHIVED === $a->status();
            }))
        ;

        $eventBus->expects(self::once())
            ->method('publish')
            ->with([], self::isInstanceOf(DomainEventInterface::class))
        ;

        $handler = new ArchiveAnimalHandler($repository, $eventBus, $clock);
        $handler($command);
    }

    public function testHandleThrowsExceptionWhenClinicMismatch(): void
    {
        $correctClinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $animalId        = AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $createdAt       = new \DateTimeImmutable('2024-01-01 10:00:00');

        $animal = Animal::reconstituteFromPersistence(
            id: $animalId,
            clinicId: $correctClinicId,
            name: 'Rex',
            species: Species::DOG,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::INTACT,
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: Identification::createEmpty(),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: null,
            status: AnimalStatus::ACTIVE,
            ownerships: [
                new \App\Animal\Domain\ValueObject\Ownership(
                    clientId: 'client-123',
                    role: \App\Animal\Domain\ValueObject\OwnershipRole::PRIMARY,
                    status: \App\Animal\Domain\ValueObject\OwnershipStatus::ACTIVE,
                    startedAt: $createdAt,
                    endedAt: null
                ),
            ],
            createdAt: $createdAt,
            updatedAt: $createdAt
        );

        $command = new ArchiveAnimal(
            clinicId: 'ffffffff-ffff-ffff-ffff-ffffffffffff',
            animalId: '01234567-89ab-cdef-0123-456789abcdef'
        );

        $repository = $this->createMock(AnimalRepositoryInterface::class);
        $eventBus   = $this->createStub(EventBusInterface::class);
        $clock      = $this->createStub(ClockInterface::class);

        $repository->expects(self::once())
            ->method('get')
            ->willReturn($animal)
        ;

        $this->expectException(AnimalClinicMismatchException::class);

        $handler = new ArchiveAnimalHandler($repository, $eventBus, $clock);
        $handler($command);
    }
}
