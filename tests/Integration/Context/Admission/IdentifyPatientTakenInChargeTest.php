<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Admission;

use App\Context\Admission\Application\Command\OpenEmergencyAdmission\OpenEmergencyAdmission;
use App\Context\Admission\Application\Command\ReconcileAdmissionPatient\ReconcileAdmissionPatient;
use App\Context\Admission\Application\Port\AdmissionReadRepositoryInterface;
use App\Context\Consultation\Application\Command\StartConsultationFromAdmission\StartConsultationFromAdmission;
use App\Context\Consultation\Application\Query\SearchConsultations\ConsultationListItemView;
use App\Context\Consultation\Application\Query\SearchConsultations\SearchConsultations;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\GetPatientAnimalLink;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\PatientAnimalLinkDto;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Context\Patient\Factory\PatientEntityFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * An emergency admitted unidentified and already taken in charge, then
 * identified at the desk: right after the identification — without waiting
 * for the async worker — the admission and its open consultation both belong
 * to the identified patient, so the flow board still shows its practitioner
 * and the cockpit shows the animal.
 */
final class IdentifyPatientTakenInChargeTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '01960000-0000-7000-8000-0000000000c1';
    private const string VET_ID    = '01960000-0000-7000-8000-0000000000c2';

    private CommandBusInterface $commandBus;

    private QueryBusInterface $queryBus;

    protected function setUp(): void
    {
        parent::setUp();

        $commandBus = self::getContainer()->get(CommandBusInterface::class);
        \assert($commandBus instanceof CommandBusInterface);
        $this->commandBus = $commandBus;

        $queryBus = self::getContainer()->get(QueryBusInterface::class);
        \assert($queryBus instanceof QueryBusInterface);
        $this->queryBus = $queryBus;

        ClinicMembershipEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withUserId(self::VET_ID)
            ->asVeterinary()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2020-01-01')])
        ;
    }

    public function testIdentifiedAsAnAnimalTheClinicAlreadyFollows(): void
    {
        $animalId       = AnimalEntityFactory::new()->withClinicId(self::CLINIC_ID)->create(['name' => 'Pacha'])->getId()->toString();
        $knownPatientId = PatientEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->identified()
            ->withAnimalLinkId($animalId)
            ->active()
            ->create()
            ->getId()
            ->toString()
        ;

        [$admissionId, $consultationId] = $this->emergencyTakenInCharge();

        $this->commandBus->dispatch(new ReconcileAdmissionPatient(
            clinicId: self::CLINIC_ID,
            admissionId: $admissionId,
            animalId: $animalId,
            animalName: 'Pacha',
        ));

        self::assertSame($knownPatientId, $this->admissionPatientId($admissionId));

        $open = $this->openConsultation($consultationId);
        self::assertSame($knownPatientId, $open->patientId, 'the open consultation follows the admission');
        self::assertSame(self::VET_ID, $open->practitionerUserId);
        self::assertSame($animalId, $this->animalOf($open->patientId));
    }

    public function testIdentifiedWithAFileCreatedOnTheSpot(): void
    {
        [$admissionId, $consultationId] = $this->emergencyTakenInCharge();
        $emergencyPatientId             = $this->admissionPatientId($admissionId);

        // « Nouvelle fiche »: a brand new animal, no patient record yet.
        $animalId = AnimalEntityFactory::new()->withClinicId(self::CLINIC_ID)->create(['name' => 'Rex'])->getId()->toString();

        $this->commandBus->dispatch(new ReconcileAdmissionPatient(
            clinicId: self::CLINIC_ID,
            admissionId: $admissionId,
            animalId: $animalId,
            animalName: 'Rex',
        ));

        self::assertSame($emergencyPatientId, $this->admissionPatientId($admissionId));

        $open = $this->openConsultation($consultationId);
        self::assertSame($emergencyPatientId, $open->patientId);
        self::assertSame($animalId, $this->animalOf($open->patientId));
    }

    /**
     * @return array{string, string}
     */
    private function emergencyTakenInCharge(): array
    {
        $admissionId = $this->commandBus->dispatch(new OpenEmergencyAdmission(
            clinicId: self::CLINIC_ID,
            intakeChannel: 'emergency_by_third_party',
            triageLevel: 'emergency',
            provisionalLabel: 'Urgence 10:28',
            observedSpecies: 'Chat',
            presenterName: 'M. Garcia',
            presenterPhone: '+33645127790',
            presenterRole: 'third_party',
            triageNotes: 'Trauma / AVP',
        ));
        \assert(\is_string($admissionId));

        $consultationId = $this->commandBus->dispatch(new StartConsultationFromAdmission(
            admissionId: $admissionId,
            startedByUserId: self::VET_ID,
        ));
        \assert(\is_string($consultationId));

        return [$admissionId, $consultationId];
    }

    private function admissionPatientId(string $admissionId): string
    {
        // The board is read by the next request: nothing of the identification's unit of work
        // must be served from the identity map.
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $em->clear();

        $admissions = self::getContainer()->get(AdmissionReadRepositoryInterface::class);
        \assert($admissions instanceof AdmissionReadRepositoryInterface);

        foreach ($admissions->findAllActiveForClinic(self::CLINIC_ID) as $item) {
            if ($item->admissionId === $admissionId) {
                return $item->patientId;
            }
        }

        self::fail('The admission is no longer on the board.');
    }

    /** The open consultations the flow board matches to its cards. */
    private function openConsultation(string $consultationId): ConsultationListItemView
    {
        $result = $this->queryBus->ask(new SearchConsultations(self::CLINIC_ID, statuses: ['OPEN']));
        \assert(\is_array($result) && \is_array($result['items'] ?? null));

        foreach ($result['items'] as $item) {
            \assert($item instanceof ConsultationListItemView);
            if ($item->consultationId === $consultationId) {
                return $item;
            }
        }

        self::fail('The consultation is no longer open.');
    }

    private function animalOf(string $patientId): ?string
    {
        $link = $this->queryBus->ask(new GetPatientAnimalLink($patientId, self::CLINIC_ID));
        \assert($link instanceof PatientAnimalLinkDto);

        return $link->animalId;
    }
}
