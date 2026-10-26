<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Billing\Infrastructure\Adapter\Admission;

use App\Context\Admission\Domain\ValueObject\AdmissionStatus;
use App\Context\Billing\Infrastructure\Adapter\Admission\DbalVisitDirectory;
use App\Fixtures\Context\Admission\Factory\AdmissionEntityFactory;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Patient\Factory\PatientEntityFactory;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DbalVisitDirectoryTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC    = '01960000-0000-7000-8000-00000000e301';
    private const string OTHER     = '01960000-0000-7000-8000-00000000e302';
    private const string ADMISSION = '01960000-0000-7000-8000-00000000e303';
    private const string PATIENT   = '01960000-0000-7000-8000-00000000e304';
    private const string ANIMAL    = '01960000-0000-7000-8000-00000000e305';

    public function testTheVisitNamesItsAnimalAndItsDay(): void
    {
        AnimalEntityFactory::new()->withId(self::ANIMAL)->withClinicId(self::CLINIC)->withName('Minou')->create();
        $this->admission(self::ANIMAL);

        $visit = $this->directory()->visitOf(self::CLINIC, self::ADMISSION);

        self::assertNotNull($visit);
        self::assertSame('Minou', $visit->animalName);
        self::assertSame('2026-10-16 07:45:00', $visit->openedAt->format('Y-m-d H:i:s'));
        self::assertNull($this->directory()->visitOf(self::OTHER, self::ADMISSION));
    }

    public function testAnUnidentifiedAnimalHasNoName(): void
    {
        $this->admission(null);

        self::assertNull($this->directory()->visitOf(self::CLINIC, self::ADMISSION)?->animalName);
    }

    private function admission(?string $animal): void
    {
        $patient = PatientEntityFactory::new()->withId(self::PATIENT)->withClinicId(self::CLINIC);
        (null !== $animal ? $patient->withAnimalLinkId($animal) : $patient)->create();

        AdmissionEntityFactory::new()
            ->withId(self::ADMISSION)
            ->withClinicId(self::CLINIC)
            ->withPatientId(self::PATIENT)
            ->create(['status' => AdmissionStatus::Active, 'openedAt' => new \DateTimeImmutable('2026-10-16 07:45:00', new \DateTimeZone('UTC'))])
        ;
    }

    private function directory(): DbalVisitDirectory
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return new DbalVisitDirectory($connection);
    }
}
