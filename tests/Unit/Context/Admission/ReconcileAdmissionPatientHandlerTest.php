<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Admission;

use App\Context\Admission\Application\Command\ReconcileAdmissionPatient\ReconcileAdmissionPatient;
use App\Context\Admission\Application\Command\ReconcileAdmissionPatient\ReconcileAdmissionPatientHandler;
use App\Context\Admission\Application\Port\PatientCreationPort;
use App\Context\Admission\Domain\Admission;
use App\Context\Admission\Domain\Repository\AdmissionRepositoryInterface;
use App\Context\Admission\Domain\ValueObject\AdmissionId;
use App\Context\Admission\Domain\ValueObject\ClinicId;
use App\Context\Admission\Domain\ValueObject\IntakeChannel;
use App\Context\Admission\Domain\ValueObject\TriageLevel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ReconcileAdmissionPatientHandlerTest extends TestCase
{
    private AdmissionRepositoryInterface&MockObject $admissionRepository;
    private PatientCreationPort&MockObject $patientCreationPort;
    private ReconcileAdmissionPatientHandler $handler;

    protected function setUp(): void
    {
        $this->admissionRepository = $this->createMock(AdmissionRepositoryInterface::class);
        $this->patientCreationPort = $this->createMock(PatientCreationPort::class);
        $this->handler             = new ReconcileAdmissionPatientHandler(
            $this->admissionRepository,
            $this->patientCreationPort,
        );
    }

    public function testDispatchesReconcileWithSourcePatientId(): void
    {
        $clinicId    = '12345678-9abc-def0-1234-56789abcdef0';
        $admissionId = '01234567-89ab-cdef-0123-456789abcdef';
        $animalId    = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $animalName  = 'Max';
        $patientId   = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';

        $admission = Admission::open(
            id: AdmissionId::fromString($admissionId),
            clinicId: ClinicId::fromString($clinicId),
            patientId: $patientId,
            isPatientIdentified: false,
            channel: IntakeChannel::Emergency,
            triage: TriageLevel::Standard,
            presenter: null,
            now: new \DateTimeImmutable('2024-06-01T10:00:00+00:00'),
        );

        $this->admissionRepository->expects(self::once())
            ->method('get')
            ->willReturn($admission)
        ;

        $this->admissionRepository->expects(self::never())
            ->method('save')
        ;

        $this->patientCreationPort->expects(self::once())
            ->method('reconcilePatientToAnimal')
            ->with(
                sourcePatientId: $patientId,
                animalId: $animalId,
                animalName: $animalName,
                clinicId: $clinicId,
            )
        ;

        ($this->handler)(new ReconcileAdmissionPatient(
            clinicId: $clinicId,
            admissionId: $admissionId,
            animalId: $animalId,
            animalName: $animalName,
        ));
    }
}
