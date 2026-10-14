<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Command\UpdateAnimalInsurance;

use App\Context\Animal\Application\Command\UpdateAnimalInsurance\UpdateAnimalInsurance;
use App\Context\Animal\Application\Command\UpdateAnimalInsurance\UpdateAnimalInsuranceHandler;
use App\Context\Animal\Domain\Animal;
use App\Context\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\Identification;
use App\Context\Animal\Domain\ValueObject\Insurance;
use App\Context\Animal\Domain\ValueObject\LifeCycle;
use App\Context\Animal\Domain\ValueObject\ReproductiveStatus;
use App\Context\Animal\Domain\ValueObject\Sex;
use App\Context\Animal\Domain\ValueObject\Species;
use App\Context\Animal\Domain\ValueObject\Transfer;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class UpdateAnimalInsuranceHandlerTest extends TestCase
{
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';
    private const string ANIMAL_ID = '01234567-89ab-cdef-0123-456789abcdef';

    public function testRecordsTheInsurance(): void
    {
        $animal = $this->animal();

        $this->handler($animal)(new UpdateAnimalInsurance(self::CLINIC_ID, self::ANIMAL_ID, ' Santévet ', 'sv-2210-4471'));

        $insurance = $animal->insurance();
        self::assertNotNull($insurance);
        self::assertSame('Santévet', $insurance->insurerName);
        self::assertSame('SV-2210-4471', $insurance->policyNumber);
    }

    public function testAnEmptyInsurerRemovesTheInsurance(): void
    {
        $animal = $this->animal();
        $animal->updateInsurance(new Insurance('Agria', 'AG-12345'), new \DateTimeImmutable());

        $this->handler($animal)(new UpdateAnimalInsurance(self::CLINIC_ID, self::ANIMAL_ID, '  ', null));

        self::assertNull($animal->insurance());
    }

    public function testANullInsurerRemovesTheInsurance(): void
    {
        $animal = $this->animal();
        $animal->updateInsurance(new Insurance('Agria', 'AG-12345'), new \DateTimeImmutable());

        $this->handler($animal)(new UpdateAnimalInsurance(self::CLINIC_ID, self::ANIMAL_ID, null, 'AG-12345'));

        self::assertNull($animal->insurance());
    }

    public function testAnInsurerWithoutPolicyNumberIsRefused(): void
    {
        $repository = $this->createMock(AnimalRepositoryInterface::class);
        $repository->method('get')->willReturn($this->animal());
        $repository->expects(self::never())->method('save');

        $this->expectException(\InvalidArgumentException::class);

        (new UpdateAnimalInsuranceHandler($repository, $this->createStub(ClockInterface::class)))(
            new UpdateAnimalInsurance(self::CLINIC_ID, self::ANIMAL_ID, 'Santévet', null),
        );
    }

    private function handler(Animal $animal): UpdateAnimalInsuranceHandler
    {
        $repository = $this->createMock(AnimalRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('get')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
                self::callback(static fn (AnimalId $id): bool => self::ANIMAL_ID === $id->toString()),
            )
            ->willReturn($animal)
        ;
        $repository->expects(self::once())->method('save')->with($animal);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-10-14 10:00:00'));

        return new UpdateAnimalInsuranceHandler($repository, $clock);
    }

    private function animal(): Animal
    {
        return Animal::create(
            id: AnimalId::fromString(self::ANIMAL_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
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
            primaryOwnerClientId: 'client-123',
            secondaryOwnerClientIds: [],
            now: new \DateTimeImmutable('2024-01-01T10:00:00+00:00'),
        );
    }
}
