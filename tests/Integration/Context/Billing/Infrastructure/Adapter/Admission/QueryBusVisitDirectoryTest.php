<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Billing\Infrastructure\Adapter\Admission;

use App\Context\Admission\Domain\ValueObject\AdmissionStatus;
use App\Context\Billing\Infrastructure\Adapter\Admission\QueryBusVisitDirectory;
use App\Fixtures\Context\Admission\Factory\AdmissionEntityFactory;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Patient\Factory\PatientEntityFactory;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * The visit chain admission → patient → animal through the public queries
 * of Admission, Patient and Animal, on the real query bus.
 */
final class QueryBusVisitDirectoryTest extends KernelTestCase
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
        self::assertSame('2026-10-16 07:45:00 UTC', $visit->openedAt->format('Y-m-d H:i:s T'));
        self::assertNull($this->directory()->visitOf(self::OTHER, self::ADMISSION), 'An admission of another clinic is unknown.');
        self::assertNull($this->directory()->visitOf(self::CLINIC, 'not-a-uuid'));
        self::assertNull($this->directory()->visitOf(self::CLINIC, self::PATIENT));
    }

    public function testAnUnidentifiedAnimalHasNoName(): void
    {
        $this->admission(null);

        self::assertNull($this->directory()->visitOf(self::CLINIC, self::ADMISSION)?->animalName);
    }

    public function testAnAnimalOfAnotherClinicHasNoName(): void
    {
        AnimalEntityFactory::new()->withId(self::ANIMAL)->withClinicId(self::OTHER)->withName('Minou')->create();
        $this->admission(self::ANIMAL);

        $visit = $this->directory()->visitOf(self::CLINIC, self::ADMISSION);

        self::assertNotNull($visit);
        self::assertNull($visit->animalName);
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

    private function directory(): QueryBusVisitDirectory
    {
        $queryBus = self::getContainer()->get(QueryBusInterface::class);
        \assert($queryBus instanceof QueryBusInterface);

        return new QueryBusVisitDirectory($queryBus);
    }
}
