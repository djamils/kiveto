<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Admission;

use App\Context\Admission\Application\Port\PatientCreationPort;
use App\Context\Admission\Application\Port\PatientReadRepositoryPort;
use App\Context\Admission\Application\Service\EmergencyAdmissionService;
use App\Context\Admission\Domain\Admission;
use App\Context\Admission\Domain\Event\AdmissionOpenedWithUnidentifiedPatient;
use App\Context\Admission\Domain\Exception\PatientAlreadyAdmittedException;
use App\Context\Admission\Domain\Repository\AdmissionRepositoryInterface;
use App\Context\Admission\Domain\ValueObject\ClinicId;
use App\Context\Admission\Domain\ValueObject\IntakeChannel;
use App\Context\Admission\Domain\ValueObject\TriageLevel;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Bus\IntegrationEventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Application\Event\IntegrationEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class EmergencyAdmissionServiceTest extends TestCase
{
    private const string CLINIC_ID    = '22222222-2222-4222-8222-222222222222';
    private const string ADMISSION_ID = '44444444-4444-4444-8444-444444444444';
    private const string ANIMAL_ID    = '55555555-5555-4555-8555-555555555555';
    private const string PATIENT_ID   = '66666666-6666-4666-8666-666666666666';

    private AdmissionRepositoryInterface&MockObject $admissions;
    private PatientCreationPort&MockObject $patientCreation;
    private PatientReadRepositoryPort&MockObject $patients;
    private IntegrationEventBusInterface&MockObject $integrationBus;
    private EmergencyAdmissionService $service;

    protected function setUp(): void
    {
        $this->admissions      = $this->createMock(AdmissionRepositoryInterface::class);
        $this->patientCreation = $this->createMock(PatientCreationPort::class);
        $this->patients        = $this->createMock(PatientReadRepositoryPort::class);
        $this->integrationBus  = $this->createMock(IntegrationEventBusInterface::class);

        $uuids = $this->createStub(UuidGeneratorInterface::class);
        $uuids->method('generate')->willReturn(self::ADMISSION_ID);
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-03-04 10:42:00'));

        $this->service = new EmergencyAdmissionService(
            $this->admissions,
            $this->patientCreation,
            $this->patients,
            $uuids,
            new DomainEventPublisher($this->createStub(EventBusInterface::class)),
            new IntegrationEventPublisher($this->integrationBus),
            $clock,
        );
    }

    public function testAFirstVisitCreatesThePatientOfTheAnimal(): void
    {
        $this->patients->method('getActivePatientIdForAnimal')->willReturn(null);
        $this->admissions->expects(self::never())->method('hasActiveAdmissionForPatient');
        $this->patientCreation->expects(self::once())
            ->method('createIdentifiedPatient')
            ->with(self::CLINIC_ID, self::ANIMAL_ID, 'Simba')
            ->willReturn(self::PATIENT_ID)
        ;
        $this->admissions->expects(self::once())
            ->method('save')
            ->with(self::callback(
                static fn (Admission $a): bool => self::PATIENT_ID === $a->patientId() && $a->isPatientIdentifiedAtOpening(),
            ))
        ;
        $this->integrationBus->expects(self::never())->method('publish');

        $id = $this->open(self::ANIMAL_ID, 'Simba');

        self::assertSame(self::ADMISSION_ID, $id);
    }

    public function testAReturningAnimalKeepsItsPatientRecord(): void
    {
        $this->patients->method('getActivePatientIdForAnimal')
            ->with(self::CLINIC_ID, self::ANIMAL_ID)
            ->willReturn(self::PATIENT_ID)
        ;
        $this->admissions->method('hasActiveAdmissionForPatient')->willReturn(false);
        $this->patientCreation->expects(self::never())->method('createIdentifiedPatient');
        $this->admissions->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (Admission $a): bool => self::PATIENT_ID === $a->patientId()))
        ;

        $this->open(self::ANIMAL_ID, 'Simba');
    }

    public function testAnAnimalAlreadyOnSiteCannotArriveTwice(): void
    {
        $this->patients->method('getActivePatientIdForAnimal')->willReturn(self::PATIENT_ID);
        $this->admissions->expects(self::once())
            ->method('hasActiveAdmissionForPatient')
            ->with(self::equalTo(ClinicId::fromString(self::CLINIC_ID)), self::PATIENT_ID)
            ->willReturn(true)
        ;
        $this->admissions->expects(self::never())->method('save');

        $this->expectException(PatientAlreadyAdmittedException::class);
        $this->expectExceptionMessage(self::PATIENT_ID);

        $this->open(self::ANIMAL_ID, 'Simba');
    }

    public function testTheChipReadAtTheDeskStaysWithAnUnidentifiedPatientOnly(): void
    {
        $this->patientCreation->method('createUnidentifiedPatient')->willReturn(self::PATIENT_ID);
        $this->patients->method('getActivePatientIdForAnimal')->willReturn(null);
        $this->patientCreation->method('createIdentifiedPatient')->willReturn(self::PATIENT_ID);

        $saved = [];
        $this->admissions->method('save')->willReturnCallback(static function (Admission $a) use (&$saved): void {
            $saved[] = $a->scannedMicrochip();
        });

        foreach ([null, self::ANIMAL_ID] as $animalId) {
            $this->service->openEmergencyAdmission(
                clinicId: ClinicId::fromString(self::CLINIC_ID),
                channel: IntakeChannel::EmergencyByThirdParty,
                triage: TriageLevel::Emergency,
                presenter: null,
                knownAnimalId: $animalId,
                animalName: null === $animalId ? null : 'Ulysse',
                provisionalLabel: null,
                observedSpecies: 'Chat',
                observedColor: null,
                scannedMicrochip: '250268500112847',
            );
        }

        self::assertSame(['250268500112847', null], $saved);
    }

    public function testAnUnidentifiedPatientGetsAProvisionalLabelAndRaisesTheObligations(): void
    {
        $this->patientCreation->expects(self::once())
            ->method('createUnidentifiedPatient')
            ->with(self::CLINIC_ID, 'Chien · 04/03 10h42', 'Chien', null)
            ->willReturn(self::PATIENT_ID)
        ;
        $this->integrationBus->expects(self::once())
            ->method('publish')
            ->with([], self::isInstanceOf(AdmissionOpenedWithUnidentifiedPatient::class))
        ;

        $this->service->openEmergencyAdmission(
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            channel: IntakeChannel::Emergency,
            triage: TriageLevel::Emergency,
            presenter: null,
            knownAnimalId: null,
            animalName: null,
            provisionalLabel: null,
            observedSpecies: 'Chien',
            observedColor: null,
            triageNotes: 'Trauma / AVP',
        );
    }

    private function open(string $animalId, string $name): string
    {
        return $this->service->openEmergencyAdmission(
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            channel: IntakeChannel::WalkIn,
            triage: TriageLevel::Standard,
            presenter: null,
            knownAnimalId: $animalId,
            animalName: $name,
            provisionalLabel: null,
            observedSpecies: null,
            observedColor: null,
        )->value();
    }
}
