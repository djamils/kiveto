<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Domain;

use App\Context\Animal\Domain\Animal;
use App\Context\Animal\Domain\Exception\AnimalArchivedCannotBeModifiedException;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\AnimalStatus;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\Identification;
use App\Context\Animal\Domain\ValueObject\Insurance;
use App\Context\Animal\Domain\ValueObject\LifeCycle;
use App\Context\Animal\Domain\ValueObject\ReproductiveStatus;
use App\Context\Animal\Domain\ValueObject\Sex;
use App\Context\Animal\Domain\ValueObject\Species;
use App\Context\Animal\Domain\ValueObject\Transfer;
use PHPUnit\Framework\TestCase;

final class AnimalInsuranceTest extends TestCase
{
    public function testAnAnimalStartsUninsured(): void
    {
        self::assertNull($this->animal(AnimalStatus::ACTIVE)->insurance());
    }

    public function testInsuranceIsRecordedThenRemoved(): void
    {
        $animal    = $this->animal(AnimalStatus::ACTIVE);
        $insurance = new Insurance('Santévet', 'SV-2210-4471');
        $now       = new \DateTimeImmutable('2026-10-14 10:00:00');

        $animal->updateInsurance($insurance, $now);

        self::assertSame($insurance, $animal->insurance());
        self::assertSame($now, $animal->updatedAt());

        $animal->updateInsurance(null, $now);

        self::assertNull($animal->insurance());
    }

    public function testAnArchivedAnimalKeepsItsInsurance(): void
    {
        $this->expectException(AnimalArchivedCannotBeModifiedException::class);

        $this->animal(AnimalStatus::ARCHIVED)->updateInsurance(new Insurance('Agria', 'AG-0001'), new \DateTimeImmutable());
    }

    public function testInsuranceIsReconstitutedFromPersistence(): void
    {
        $insurance = new Insurance('Bulle Bleue', 'BB-77812-03');

        self::assertSame($insurance, $this->animal(AnimalStatus::ACTIVE, $insurance)->insurance());
    }

    private function animal(AnimalStatus $status, ?Insurance $insurance = null): Animal
    {
        $at = new \DateTimeImmutable('2026-01-01 10:00:00');

        return Animal::reconstituteFromPersistence(
            id: AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0'),
            name: 'Minette',
            species: Species::CAT,
            sex: Sex::FEMALE,
            reproductiveStatus: ReproductiveStatus::NEUTERED,
            isMixedBreed: false,
            breedName: 'Maine coon',
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: Identification::createEmpty(),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: null,
            status: $status,
            ownerships: [],
            createdAt: $at,
            updatedAt: $at,
            insurance: $insurance,
        );
    }
}
