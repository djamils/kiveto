<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Prescription\Infrastructure\Adapter;

use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\ClinicId as ConsultationClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId as ConsultationAggregateId;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Context\Prescription\Application\Port\PatientDirectoryInterface;
use App\Context\Prescription\Application\Port\PrescriberDirectoryInterface;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Patient\Factory\PatientEntityFactory;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * The two directories the ordonnance is built from: who signs it, and who it is
 * for.
 */
final class PrescriptionDirectoriesTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID       = '01960000-0000-7000-8000-00000000e001';
    private const string OTHER_CLINIC    = '01960000-0000-7000-8000-00000000e002';
    private const string USER_ID         = '01960000-0000-7000-8000-00000000e003';
    private const string MEMBERSHIP_ID   = '01960000-0000-7000-8000-00000000e004';
    private const string PROFILE_ID      = '01960000-0000-7000-8000-00000000e005';
    private const string CONSULTATION_ID = '01960000-0000-7000-8000-00000000e006';
    private const string PATIENT_ID      = '01960000-0000-7000-8000-00000000e007';
    private const string ANIMAL_ID       = '01960000-0000-7000-8000-00000000e008';
    private const string CLIENT_ID       = '01960000-0000-7000-8000-00000000e009';

    private PrescriberDirectoryInterface $prescribers;
    private PatientDirectoryInterface $patients;
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::getContainer();

        $prescribers = $container->get(PrescriberDirectoryInterface::class);
        \assert($prescribers instanceof PrescriberDirectoryInterface);
        $this->prescribers = $prescribers;

        $patients = $container->get(PatientDirectoryInterface::class);
        \assert($patients instanceof PatientDirectoryInterface);
        $this->patients = $patients;

        $connection = $container->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->connection = $connection;
    }

    // ── Prescriber ──

    public function testAPractitionerSigningIdentityIsRead(): void
    {
        $this->membership();
        $this->staffProfile('Rousseau', 'Éric', 'Rousseau', 'FR-12345', 'DR');

        $prescriber = $this->prescribers->forUser(self::USER_ID, self::CLINIC_ID);

        self::assertNotNull($prescriber);
        self::assertSame(self::USER_ID, $prescriber->userId);
        self::assertSame('Rousseau', $prescriber->displayName);
        self::assertSame('FR-12345', $prescriber->registrationNumber);
        self::assertSame('DR', $prescriber->professionalTitle);
    }

    public function testAProfileWithoutADisplayNameFallsBackOnTheFullName(): void
    {
        $this->membership();
        // The column is NOT NULL, so an unnamed profile shows up as blank.
        $this->staffProfile('  ', 'Julie', 'Martin', null, null);

        $prescriber = $this->prescribers->forUser(self::USER_ID, self::CLINIC_ID);

        self::assertNotNull($prescriber);
        self::assertSame('Julie Martin', $prescriber->displayName);
        self::assertNull($prescriber->registrationNumber);
    }

    public function testAProfileWithNoNameAtAllIsNoPrescriber(): void
    {
        $this->membership();
        $this->staffProfile('  ', '  ', '  ', null, null);

        self::assertNull($this->prescribers->forUser(self::USER_ID, self::CLINIC_ID));
    }

    public function testAPractitionerWithoutAProfileHereIsUnknown(): void
    {
        $this->membership();

        self::assertNull($this->prescribers->forUser(self::USER_ID, self::CLINIC_ID));
    }

    public function testAPractitionerOfAnotherClinicIsUnknown(): void
    {
        $this->membership();
        $this->staffProfile('Rousseau', 'Éric', 'Rousseau', 'FR-12345', 'DR');

        self::assertNull($this->prescribers->forUser(self::USER_ID, self::OTHER_CLINIC));
    }

    // ── Patient ──

    public function testTheAnimalAndItsOwnerAreRead(): void
    {
        $this->consultation();
        $this->client();
        $this->ownership('primary', 'active');

        $patient = $this->patients->forConsultation(self::CONSULTATION_ID, self::CLINIC_ID);

        self::assertNotNull($patient);
        self::assertSame(self::ANIMAL_ID, $patient->animalId);
        self::assertSame('Rex', $patient->animalName);
        self::assertSame('dog', $patient->species);
        self::assertSame('Labrador', $patient->breed);
        self::assertSame('250269801234567', $patient->microchipNumber);
        self::assertSame('2016-05-01', $patient->birthDate?->format('Y-m-d'));
        self::assertSame(self::CLIENT_ID, $patient->ownerClientId);
        self::assertSame('Sophie Dupont', $patient->ownerName);
        self::assertSame(['12 rue des Lilas', '75011 Paris'], $patient->ownerAddressLines);
    }

    public function testAnAnimalWithoutAnActiveOwnerHasNoHolder(): void
    {
        $this->consultation();
        $this->client();
        $this->ownership('primary', 'ended');

        $patient = $this->patients->forConsultation(self::CONSULTATION_ID, self::CLINIC_ID);

        self::assertNotNull($patient);
        self::assertNull($patient->ownerName);
        self::assertNull($patient->ownerClientId);
        self::assertSame([], $patient->ownerAddressLines);
    }

    public function testAnOwnerWithoutAnAddressIsStillNamed(): void
    {
        $this->consultation();
        $this->client(street: null, zip: null, city: null);
        $this->ownership('primary', 'active');

        $patient = $this->patients->forConsultation(self::CONSULTATION_ID, self::CLINIC_ID);

        self::assertNotNull($patient);
        self::assertSame('Sophie Dupont', $patient->ownerName);
        self::assertSame([], $patient->ownerAddressLines);
    }

    public function testAnUnknownConsultationHasNoPatient(): void
    {
        self::assertNull($this->patients->forConsultation(self::CONSULTATION_ID, self::CLINIC_ID));
    }

    public function testAConsultationOfAnotherClinicIsInvisible(): void
    {
        $this->consultation();

        self::assertNull($this->patients->forConsultation(self::CONSULTATION_ID, self::OTHER_CLINIC));
    }

    private function membership(): void
    {
        $this->connection->insert('clinic__clinic_memberships', [
            'id'             => Uuid::fromString(self::MEMBERSHIP_ID)->toBinary(),
            'clinic_id'      => Uuid::fromString(self::CLINIC_ID)->toBinary(),
            'user_id'        => Uuid::fromString(self::USER_ID)->toBinary(),
            'role'           => 'VETERINARY',
            'engagement'     => 'EMPLOYEE',
            'status'         => 'ACTIVE',
            'valid_from_utc' => '2026-01-01 00:00:00',
            'created_at_utc' => '2026-01-01 00:00:00',
            'is_default'     => 1,
        ]);
    }

    private function staffProfile(
        string $displayName,
        string $firstName,
        string $lastName,
        ?string $registrationNumber,
        ?string $professionalTitle,
    ): void {
        $this->connection->insert('clinic__staff_profiles', [
            'id'                   => Uuid::fromString(self::PROFILE_ID)->toBinary(),
            'membership_id'        => Uuid::fromString(self::MEMBERSHIP_ID)->toBinary(),
            'first_name'           => $firstName,
            'last_name'            => $lastName,
            'display_name'         => $displayName,
            'agenda_color'         => '#4f86c6',
            'sort_order'           => 0,
            'is_visible_in_agenda' => 1,
            'registration_number'  => $registrationNumber,
            'professional_title'   => $professionalTitle,
            'created_at_utc'       => '2026-01-01 00:00:00',
            'updated_at_utc'       => '2026-01-01 00:00:00',
        ]);
    }

    private function consultation(): void
    {
        AnimalEntityFactory::new()
            ->withId(self::ANIMAL_ID)
            ->withClinicId(self::CLINIC_ID)
            ->withName('Rex')
            ->withBreed('Labrador', \App\Context\Animal\Domain\ValueObject\Species::DOG)
            ->withMicrochip('250269801234567')
            ->create(['birthDate' => new \DateTimeImmutable('2016-05-01')])
        ;

        PatientEntityFactory::new()
            ->withId(self::PATIENT_ID)
            ->withClinicId(self::CLINIC_ID)
            ->withAnimalLinkId(self::ANIMAL_ID)
            ->create()
        ;

        $repository = self::getContainer()->get(ConsultationRepositoryInterface::class);
        \assert($repository instanceof ConsultationRepositoryInterface);

        $repository->save(Consultation::startFromAdmission(
            ConsultationAggregateId::fromString(self::CONSULTATION_ID),
            ConsultationClinicId::fromString(self::CLINIC_ID),
            AdmissionId::fromString(Uuid::v7()->toRfc4122()),
            PatientId::fromString(self::PATIENT_ID),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-08-22 10:00:00'),
        ));
    }

    private function client(
        ?string $street = '12 rue des Lilas',
        ?string $zip = '75011',
        ?string $city = 'Paris',
    ): void {
        $this->connection->insert('client__clients', [
            'id'                           => Uuid::fromString(self::CLIENT_ID)->toBinary(),
            'clinic_id'                    => Uuid::fromString(self::CLINIC_ID)->toBinary(),
            'first_name'                   => 'Sophie',
            'last_name'                    => 'Dupont',
            'status'                       => 'ACTIVE',
            'created_at'                   => '2026-08-22 10:00:00',
            'updated_at'                   => '2026-08-22 10:00:00',
            'postal_address_street_line_1' => $street,
            'postal_address_postal_code'   => $zip,
            'postal_address_city'          => $city,
            'postal_address_country_code'  => 'FR',
            'version'                      => 1,
        ]);
    }

    private function ownership(string $role, string $status): void
    {
        $this->connection->insert('animal__ownerships', [
            'id'         => Uuid::v7()->toBinary(),
            'animal_id'  => Uuid::fromString(self::ANIMAL_ID)->toBinary(),
            'client_id'  => Uuid::fromString(self::CLIENT_ID)->toBinary(),
            'role'       => $role,
            'status'     => $status,
            'started_at' => '2026-01-01 10:00:00',
        ]);
    }
}
