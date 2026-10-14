<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Billing\Infrastructure\Adapter\Client;

use App\Context\Admission\Domain\ValueObject\AdmissionStatus;
use App\Context\Billing\Application\Port\PayerDirectoryInterface;
use App\Fixtures\Context\Admission\Factory\AdmissionEntityFactory;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Patient\Factory\PatientEntityFactory;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * Walking admission → patient → animal → owner across four contexts.
 */
final class DbalPayerDirectoryTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID    = '01960000-0000-7000-8000-00000000d001';
    private const string ADMISSION_ID = '01960000-0000-7000-8000-00000000d002';
    private const string PATIENT_ID   = '01960000-0000-7000-8000-00000000d003';
    private const string ANIMAL_ID    = '01960000-0000-7000-8000-00000000d004';
    private const string CLIENT_ID    = '01960000-0000-7000-8000-00000000d005';
    private const string SECOND_OWNER = '01960000-0000-7000-8000-00000000d006';

    private PayerDirectoryInterface $directory;
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $directory = self::getContainer()->get(PayerDirectoryInterface::class);
        \assert($directory instanceof PayerDirectoryInterface);
        $this->directory = $directory;

        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->connection = $connection;
    }

    public function testThePrimaryOwnerOfTheAdmittedAnimalPays(): void
    {
        $this->admission();
        $this->client(self::CLIENT_ID, 'Sophie', 'Dupont', '12 rue des Lilas', 'Bâtiment B', '75011', 'Paris');
        $this->ownership(self::CLIENT_ID, 'primary', 'active');

        $payer = $this->directory->forAdmission(self::ADMISSION_ID, self::CLINIC_ID);

        self::assertNotNull($payer);
        self::assertSame(self::CLIENT_ID, $payer->clientId);
        self::assertSame('Sophie Dupont', $payer->displayName);
        self::assertSame(['12 rue des Lilas', 'Bâtiment B', '75011 Paris'], $payer->addressLines);
    }

    public function testASecondaryOwnerDoesNotPay(): void
    {
        $this->admission();
        $this->client(self::CLIENT_ID, 'Sophie', 'Dupont', null, null, null, null);
        $this->ownership(self::CLIENT_ID, 'secondary', 'active');

        self::assertNull($this->directory->forAdmission(self::ADMISSION_ID, self::CLINIC_ID));
    }

    public function testAnEndedOwnershipDoesNotPay(): void
    {
        $this->admission();
        $this->client(self::CLIENT_ID, 'Sophie', 'Dupont', null, null, null, null);
        $this->ownership(self::CLIENT_ID, 'primary', 'ended');

        self::assertNull($this->directory->forAdmission(self::ADMISSION_ID, self::CLINIC_ID));
    }

    public function testAnOwnerWithoutAnAddressStillPays(): void
    {
        $this->admission();
        $this->client(self::CLIENT_ID, 'Sophie', 'Dupont', null, null, null, null);
        $this->ownership(self::CLIENT_ID, 'primary', 'active');

        $payer = $this->directory->forAdmission(self::ADMISSION_ID, self::CLINIC_ID);

        self::assertNotNull($payer);
        self::assertSame([], $payer->addressLines);
    }

    public function testAnOwnerWithABlankNameHasNoPayer(): void
    {
        $this->admission();
        $this->client(self::CLIENT_ID, ' ', ' ', null, null, null, null);
        $this->ownership(self::CLIENT_ID, 'primary', 'active');

        self::assertNull($this->directory->forAdmission(self::ADMISSION_ID, self::CLINIC_ID));
    }

    public function testAnAnimalWithoutAnOwnerHasNoPayer(): void
    {
        $this->admission();

        self::assertNull($this->directory->forAdmission(self::ADMISSION_ID, self::CLINIC_ID));
    }

    public function testTheOldestPrimaryOwnerIsTheOneBilled(): void
    {
        $this->admission();
        $this->client(self::CLIENT_ID, 'Sophie', 'Dupont', null, null, null, null);
        $this->client(self::SECOND_OWNER, 'Marc', 'Lefebvre', null, null, null, null);
        $this->ownership(self::SECOND_OWNER, 'primary', 'active', '2026-02-01 10:00:00');
        $this->ownership(self::CLIENT_ID, 'primary', 'active', '2025-01-01 10:00:00');

        $payer = $this->directory->forAdmission(self::ADMISSION_ID, self::CLINIC_ID);

        self::assertNotNull($payer);
        self::assertSame('Sophie Dupont', $payer->displayName);
    }

    public function testAnUnknownVisitHasNoPayer(): void
    {
        self::assertNull($this->directory->forAdmission(self::ADMISSION_ID, self::CLINIC_ID));
    }

    public function testAVisitOfAnotherClinicIsInvisible(): void
    {
        $this->admission();
        $this->client(self::CLIENT_ID, 'Sophie', 'Dupont', null, null, null, null);
        $this->ownership(self::CLIENT_ID, 'primary', 'active');

        self::assertNull($this->directory->forAdmission(
            self::ADMISSION_ID,
            '01960000-0000-7000-8000-00000000d009',
        ));
    }

    public function testAClientOfTheClinicPaysForWhatTheDeskSoldThem(): void
    {
        $this->client(self::CLIENT_ID, 'Sophie', 'Dupont', '12 rue des Lilas', null, '75011', 'Paris');

        $payer = $this->directory->forClient(self::CLIENT_ID, self::CLINIC_ID);

        self::assertNotNull($payer);
        self::assertSame(self::CLIENT_ID, $payer->clientId);
        self::assertSame('Sophie Dupont', $payer->displayName);
        self::assertSame(['12 rue des Lilas', '75011 Paris'], $payer->addressLines);
    }

    public function testAnotherClinicsClientIsNotFound(): void
    {
        $this->client(self::CLIENT_ID, 'Sophie', 'Dupont', null, null, null, null);

        self::assertNull($this->directory->forClient(self::CLIENT_ID, '01960000-0000-7000-8000-00000000d009'));
        self::assertNull($this->directory->forClient(self::SECOND_OWNER, self::CLINIC_ID));
    }

    private function admission(): void
    {
        AnimalEntityFactory::new()
            ->withId(self::ANIMAL_ID)
            ->withClinicId(self::CLINIC_ID)
            ->withName('Rex')
            ->dog()
            ->create()
        ;

        PatientEntityFactory::new()
            ->withId(self::PATIENT_ID)
            ->withClinicId(self::CLINIC_ID)
            ->withAnimalLinkId(self::ANIMAL_ID)
            ->create()
        ;

        AdmissionEntityFactory::new()
            ->withId(self::ADMISSION_ID)
            ->withClinicId(self::CLINIC_ID)
            ->withPatientId(self::PATIENT_ID)
            ->create(['status' => AdmissionStatus::Active])
        ;
    }

    private function ownership(
        string $clientId,
        string $role,
        string $status,
        string $startedAt = '2026-01-01 10:00:00',
    ): void {
        $this->connection->insert('animal__ownerships', [
            'id'         => Uuid::v7()->toBinary(),
            'animal_id'  => Uuid::fromString(self::ANIMAL_ID)->toBinary(),
            'client_id'  => Uuid::fromString($clientId)->toBinary(),
            'role'       => $role,
            'status'     => $status,
            'started_at' => $startedAt,
        ]);
    }

    private function client(
        string $clientId,
        string $firstName,
        string $lastName,
        ?string $line1,
        ?string $line2,
        ?string $zip,
        ?string $city,
    ): void {
        $this->connection->insert('client__clients', [
            'id'                           => Uuid::fromString($clientId)->toBinary(),
            'clinic_id'                    => Uuid::fromString(self::CLINIC_ID)->toBinary(),
            'first_name'                   => $firstName,
            'last_name'                    => $lastName,
            'status'                       => 'ACTIVE',
            'created_at'                   => '2026-08-22 10:00:00',
            'updated_at'                   => '2026-08-22 10:00:00',
            'postal_address_street_line_1' => $line1,
            'postal_address_street_line_2' => $line2,
            'postal_address_postal_code'   => $zip,
            'postal_address_city'          => $city,
            'postal_address_country_code'  => 'FR',
            'version'                      => 1,
        ]);
    }
}
