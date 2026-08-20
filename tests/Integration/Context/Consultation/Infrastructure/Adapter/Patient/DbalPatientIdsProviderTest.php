<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Consultation\Infrastructure\Adapter\Patient;

use App\Context\Consultation\Application\Port\PatientIdsProviderInterface;
use App\Fixtures\Context\Patient\Factory\PatientEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DbalPatientIdsProviderTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID       = '11111111-1111-4111-8111-111111111111';
    private const string OTHER_CLINIC_ID = '22222222-2222-4222-8222-222222222222';
    private const string ANIMAL_ID       = '33333333-3333-4333-8333-333333333333';
    private const string OTHER_ANIMAL_ID = '44444444-4444-4444-8444-444444444444';
    private const string PATIENT_A       = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa1';
    private const string PATIENT_B       = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa2';
    private const string PATIENT_C       = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa3';
    private const string PATIENT_D       = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaa4';

    private PatientIdsProviderInterface $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $provider = self::getContainer()->get(PatientIdsProviderInterface::class);
        \assert($provider instanceof PatientIdsProviderInterface);
        $this->provider = $provider;
    }

    public function testReturnsEveryPatientOfTheClinicLinkedToTheAnimal(): void
    {
        // Two patient rows of the clinic point at the same animal — this is what
        // reconciliation leaves behind.
        $this->createPatient(self::PATIENT_A, self::CLINIC_ID, self::ANIMAL_ID);
        $this->createPatient(self::PATIENT_B, self::CLINIC_ID, self::ANIMAL_ID);
        // Same clinic, different animal.
        $this->createPatient(self::PATIENT_C, self::CLINIC_ID, self::OTHER_ANIMAL_ID);
        // Same animal, different clinic.
        $this->createPatient(self::PATIENT_D, self::OTHER_CLINIC_ID, self::ANIMAL_ID);

        $patientIds = $this->provider->findPatientIdsForAnimal(self::ANIMAL_ID, self::CLINIC_ID);
        sort($patientIds);

        self::assertSame([self::PATIENT_A, self::PATIENT_B], $patientIds);
    }

    public function testReturnsEmptyArrayForAnUnknownAnimal(): void
    {
        $this->createPatient(self::PATIENT_A, self::CLINIC_ID, self::ANIMAL_ID);

        self::assertSame([], $this->provider->findPatientIdsForAnimal(
            '99999999-9999-4999-8999-999999999999',
            self::CLINIC_ID,
        ));
    }

    public function testReturnsEmptyArrayForANonUuidAnimalId(): void
    {
        self::assertSame([], $this->provider->findPatientIdsForAnimal('not-a-uuid', self::CLINIC_ID));
    }

    public function testReturnsEmptyArrayForANonUuidClinicId(): void
    {
        self::assertSame([], $this->provider->findPatientIdsForAnimal(self::ANIMAL_ID, 'not-a-uuid'));
    }

    private function createPatient(string $patientId, string $clinicId, string $animalId): void
    {
        PatientEntityFactory::new()
            ->withId($patientId)
            ->withClinicId($clinicId)
            ->withAnimalLinkId($animalId)
            ->active()
            ->create()
        ;
    }
}
