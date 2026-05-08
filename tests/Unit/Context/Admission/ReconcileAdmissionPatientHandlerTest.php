<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Admission;

use App\Context\Admission\Application\Command\ReconcileAdmissionPatient\ReconcileAdmissionPatient;
use App\Context\Admission\Application\Command\ReconcileAdmissionPatient\ReconcileAdmissionPatientHandler;
use App\Context\Admission\Application\Port\PatientCreationPort;
use App\Context\Admission\Application\Port\PatientReadRepositoryPort;
use App\Context\Admission\Domain\Admission;
use App\Context\Admission\Domain\Repository\AdmissionRepositoryInterface;
use App\Context\Admission\Domain\ValueObject\AdmissionId;
use App\Context\Admission\Domain\ValueObject\ClinicId;
use App\Context\Admission\Domain\ValueObject\IntakeChannel;
use App\Context\Admission\Domain\ValueObject\TriageLevel;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class ReconcileAdmissionPatientHandlerTest extends TestCase
{
    private AdmissionRepositoryInterface&MockObject $admissionRepository;
    private PatientCreationPort&MockObject $patientCreationPort;
    private PatientReadRepositoryPort&Stub $patientReadRepository;
    private Connection&MockObject $connection;

    private string $clinicId    = '12345678-9abc-def0-1234-56789abcdef0';
    private string $admissionId = '01234567-89ab-cdef-0123-456789abcdef';
    private string $animalId    = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
    private string $animalName  = 'Max';
    private string $patientId   = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';

    protected function setUp(): void
    {
        $this->admissionRepository   = $this->createMock(AdmissionRepositoryInterface::class);
        $this->patientCreationPort   = $this->createMock(PatientCreationPort::class);
        $this->patientReadRepository = $this->createStub(PatientReadRepositoryPort::class);
        $this->connection            = $this->createMock(Connection::class);
    }

    public function testLinkPathDispatchesPortAndDoesNotUpdateAdmission(): void
    {
        $this->admissionRepository->expects(self::once())->method('get')->willReturn($this->makeAdmission());
        $this->patientCreationPort->expects(self::once())->method('reconcilePatientToAnimal');

        // Link path: active patient for animal == source patient (already linked)
        $this->patientReadRepository->method('getActivePatientIdForAnimal')->willReturn($this->patientId);

        $this->connection->expects(self::never())->method('executeStatement');

        ($this->makeHandler())(new ReconcileAdmissionPatient(
            clinicId: $this->clinicId,
            admissionId: $this->admissionId,
            animalId: $this->animalId,
            animalName: $this->animalName,
        ));
    }

    public function testReconcilePathUpdatesAdmissionPatientIdInline(): void
    {
        $targetPatientId = 'cccccccc-cccc-cccc-cccc-cccccccccccc';

        $this->admissionRepository->expects(self::once())->method('get')->willReturn($this->makeAdmission());
        $this->patientCreationPort->expects(self::once())->method('reconcilePatientToAnimal');

        // Reconcile path: active patient for animal is different (target took over)
        $this->patientReadRepository->method('getActivePatientIdForAnimal')->willReturn($targetPatientId);

        $this->connection->expects(self::once())
            ->method('executeStatement')
            ->with(
                self::stringContains('UPDATE admission__admissions'),
                self::arrayHasKey('targetId'),
            )
        ;

        ($this->makeHandler())(new ReconcileAdmissionPatient(
            clinicId: $this->clinicId,
            admissionId: $this->admissionId,
            animalId: $this->animalId,
            animalName: $this->animalName,
        ));
    }

    private function makeHandler(): ReconcileAdmissionPatientHandler
    {
        return new ReconcileAdmissionPatientHandler(
            $this->admissionRepository,
            $this->patientCreationPort,
            $this->patientReadRepository,
            $this->connection,
        );
    }

    private function makeAdmission(): Admission
    {
        return Admission::open(
            id: AdmissionId::fromString($this->admissionId),
            clinicId: ClinicId::fromString($this->clinicId),
            patientId: $this->patientId,
            isPatientIdentified: false,
            channel: IntakeChannel::Emergency,
            triage: TriageLevel::Standard,
            presenter: null,
            now: new \DateTimeImmutable('2024-06-01T10:00:00+00:00'),
        );
    }
}
