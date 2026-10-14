<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Command\UpdateAnimalLifeCycle;

use App\Context\Animal\Application\Command\UpdateAnimalLifeCycle\UpdateAnimalLifeCycle;
use App\Context\Animal\Application\Command\UpdateAnimalLifeCycle\UpdateAnimalLifeCycleHandler;
use App\Context\Animal\Domain\Animal;
use App\Context\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\BodyDisposal;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\DeathCause;
use App\Context\Animal\Domain\ValueObject\Identification;
use App\Context\Animal\Domain\ValueObject\LifeCycle;
use App\Context\Animal\Domain\ValueObject\ReproductiveStatus;
use App\Context\Animal\Domain\ValueObject\Sex;
use App\Context\Animal\Domain\ValueObject\Species;
use App\Context\Animal\Domain\ValueObject\Transfer;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class UpdateAnimalLifeCycleDeathDetailsTest extends TestCase
{
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';
    private const string ANIMAL_ID = '01234567-89ab-cdef-0123-456789abcdef';

    public function testRecordsTheCircumstancesAndTheBodysDisposal(): void
    {
        $animal = $this->handle('euthanasia', 'returned_to_owner');

        self::assertSame(DeathCause::EUTHANASIA, $animal->lifeCycle()->deathCause);
        self::assertSame(BodyDisposal::RETURNED_TO_OWNER, $animal->lifeCycle()->bodyDisposal);
    }

    public function testTheDetailsMayBeLeftOut(): void
    {
        $animal = $this->handle(null, null);

        self::assertNull($animal->lifeCycle()->deathCause);
        self::assertNull($animal->lifeCycle()->bodyDisposal);
    }

    public function testTheCommandDefaultsToNoDetails(): void
    {
        $command = new UpdateAnimalLifeCycle(self::CLINIC_ID, self::ANIMAL_ID, 'deceased', '2026-04-18', null);

        self::assertNull($command->deathCause);
        self::assertNull($command->bodyDisposal);
    }

    private function handle(?string $cause, ?string $disposal): Animal
    {
        $animal = Animal::create(
            id: AnimalId::fromString(self::ANIMAL_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            name: 'Tigrou',
            species: Species::CAT,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::NEUTERED,
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: Identification::createEmpty(),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: null,
            primaryOwnerClientId: 'client-123',
            secondaryOwnerClientIds: [],
            now: new \DateTimeImmutable('2024-01-01T10:00:00+00:00'),
        );

        $repository = $this->createMock(AnimalRepositoryInterface::class);
        $repository->method('get')->willReturn($animal);
        $repository->expects(self::once())->method('save')->with($animal);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-18 10:00:00'));

        (new UpdateAnimalLifeCycleHandler($repository, $this->createStub(EventBusInterface::class), $clock))(new UpdateAnimalLifeCycle(
            clinicId: self::CLINIC_ID,
            animalId: self::ANIMAL_ID,
            lifeStatus: 'deceased',
            deceasedAt: '2026-04-18',
            missingSince: null,
            deathCause: $cause,
            bodyDisposal: $disposal,
        ));

        return $animal;
    }
}
