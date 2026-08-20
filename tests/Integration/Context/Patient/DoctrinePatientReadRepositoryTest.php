<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Patient;

use App\Context\Patient\Application\Port\PatientReadRepositoryInterface;
use App\Fixtures\Context\Patient\Factory\PatientEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrinePatientReadRepositoryTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID  = '01950000-0000-7000-0000-0000000000c1';
    private const string PATIENT_ID = '01950000-0000-7000-0000-0000000000d1';
    private const string ANIMAL_ID  = '01950000-0000-7000-0000-0000000000e1';

    public function testFindAnimalLinkForReconciledPatient(): void
    {
        PatientEntityFactory::new()
            ->withId(self::PATIENT_ID)
            ->withClinicId(self::CLINIC_ID)
            ->withAnimalLinkId(self::ANIMAL_ID)
            ->create(['displayLabelValue' => 'Luna'])
        ;

        $dto = $this->readRepository()->findAnimalLink(self::CLINIC_ID, self::PATIENT_ID);

        self::assertNotNull($dto);
        self::assertSame(self::PATIENT_ID, $dto->patientId);
        self::assertSame(self::ANIMAL_ID, $dto->animalId);
        self::assertSame('Luna', $dto->displayLabel);
        self::assertNull($dto->observedSpecies);
        self::assertNull($dto->observedColor);
    }

    public function testFindAnimalLinkForUnreconciledPatient(): void
    {
        PatientEntityFactory::new()
            ->withId(self::PATIENT_ID)
            ->withClinicId(self::CLINIC_ID)
            ->provisional('Chat roux inconnu')
            ->create([
                'observedSpecies' => 'cat',
                'observedColor'   => 'roux',
            ])
        ;

        $dto = $this->readRepository()->findAnimalLink(self::CLINIC_ID, self::PATIENT_ID);

        self::assertNotNull($dto);
        self::assertSame(self::PATIENT_ID, $dto->patientId);
        self::assertNull($dto->animalId);
        self::assertSame('Chat roux inconnu', $dto->displayLabel);
        self::assertSame('cat', $dto->observedSpecies);
        self::assertSame('roux', $dto->observedColor);
    }

    public function testFindAnimalLinkReturnsNullWhenPatientUnknown(): void
    {
        self::assertNull($this->readRepository()->findAnimalLink(self::CLINIC_ID, Uuid::v7()->toRfc4122()));
    }

    public function testFindAnimalLinkReturnsNullForOtherClinic(): void
    {
        PatientEntityFactory::new()
            ->withId(self::PATIENT_ID)
            ->withClinicId(self::CLINIC_ID)
            ->create()
        ;

        self::assertNull($this->readRepository()->findAnimalLink(Uuid::v7()->toRfc4122(), self::PATIENT_ID));
    }

    private function readRepository(): PatientReadRepositoryInterface
    {
        $repository = static::getContainer()->get(PatientReadRepositoryInterface::class);
        \assert($repository instanceof PatientReadRepositoryInterface);

        return $repository;
    }
}
