<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Command\ReplaceAnimalOwners;

use App\Context\Animal\Application\Command\ReplaceAnimalOwners\ReplaceAnimalOwners;
use App\Context\Animal\Application\Command\ReplaceAnimalOwners\ReplaceAnimalOwnersHandler;
use App\Context\Animal\Domain\Animal;
use App\Context\Animal\Domain\Exception\AnimalClinicMismatchException;
use App\Context\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\AnimalStatus;
use App\Context\Animal\Domain\ValueObject\Identification;
use App\Context\Animal\Domain\ValueObject\LifeCycle;
use App\Context\Animal\Domain\ValueObject\ReproductiveStatus;
use App\Context\Animal\Domain\ValueObject\Sex;
use App\Context\Animal\Domain\ValueObject\Species;
use App\Context\Animal\Domain\ValueObject\Transfer;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class ReplaceAnimalOwnersHandlerTest extends TestCase
{
    public function testHandleReplacesOwners(): void
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
                new \App\Context\Animal\Domain\ValueObject\Ownership(
                    clientId: 'client-123',
                    role: \App\Context\Animal\Domain\ValueObject\OwnershipRole::PRIMARY,
                    status: \App\Context\Animal\Domain\ValueObject\OwnershipStatus::ACTIVE,
                    startedAt: $createdAt,
                    endedAt: null
                ),
            ],
            createdAt: $createdAt,
            updatedAt: $createdAt
        );

        $command = new ReplaceAnimalOwners(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            animalId: '01234567-89ab-cdef-0123-456789abcdef',
            primaryOwnerClientId: 'client-new',
            secondaryOwnerClientIds: ['client-sec1', 'client-sec2'],
        );

        $repository = $this->createMock(AnimalRepositoryInterface::class);
        $eventBus   = $this->createMock(EventBusInterface::class);
        $clock      = $this->createMock(ClockInterface::class);

        $repository->expects(self::once())
            ->method('get')
            ->willReturn($animal)
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
        ;

        $handler = new ReplaceAnimalOwnersHandler($repository, $eventBus, $clock);
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
                new \App\Context\Animal\Domain\ValueObject\Ownership(
                    clientId: 'client-123',
                    role: \App\Context\Animal\Domain\ValueObject\OwnershipRole::PRIMARY,
                    status: \App\Context\Animal\Domain\ValueObject\OwnershipStatus::ACTIVE,
                    startedAt: $createdAt,
                    endedAt: null
                ),
            ],
            createdAt: $createdAt,
            updatedAt: $createdAt
        );

        $command = new ReplaceAnimalOwners(
            clinicId: 'ffffffff-ffff-ffff-ffff-ffffffffffff',
            animalId: '01234567-89ab-cdef-0123-456789abcdef',
            primaryOwnerClientId: 'client-new',
            secondaryOwnerClientIds: [],
        );

        $repository = $this->createMock(AnimalRepositoryInterface::class);
        $eventBus   = $this->createStub(EventBusInterface::class);
        $clock      = $this->createStub(ClockInterface::class);

        $repository->expects(self::once())
            ->method('get')
            ->willReturn($animal)
        ;

        $this->expectException(AnimalClinicMismatchException::class);

        $handler = new ReplaceAnimalOwnersHandler($repository, $eventBus, $clock);
        $handler($command);
    }
}
