<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Admission;

use App\Context\Admission\Application\Command\UpdateAdmissionLocationStatus\UpdateAdmissionLocationStatus;
use App\Context\Admission\Application\Command\UpdateAdmissionLocationStatus\UpdateAdmissionLocationStatusHandler;
use App\Context\Admission\Domain\Admission;
use App\Context\Admission\Domain\Exception\AdmissionNotFoundException;
use App\Context\Admission\Domain\Repository\AdmissionRepositoryInterface;
use App\Context\Admission\Domain\ValueObject\AdmissionId;
use App\Context\Admission\Domain\ValueObject\ClinicId;
use App\Context\Admission\Domain\ValueObject\IntakeChannel;
use App\Context\Admission\Domain\ValueObject\LocationStatusValue;
use App\Context\Admission\Domain\ValueObject\TriageLevel;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UpdateAdmissionLocationStatusHandlerTest extends TestCase
{
    private const string CLINIC_ID    = '22222222-2222-4222-8222-222222222222';
    private const string ADMISSION_ID = '44444444-4444-4444-8444-444444444444';
    private const string PATIENT_ID   = '66666666-6666-4666-8666-666666666666';

    private AdmissionRepositoryInterface&MockObject $admissionRepository;
    private ClockInterface&MockObject $clock;
    private UpdateAdmissionLocationStatusHandler $handler;

    protected function setUp(): void
    {
        $this->admissionRepository = $this->createMock(AdmissionRepositoryInterface::class);
        $this->clock               = $this->createMock(ClockInterface::class);

        $this->handler = new UpdateAdmissionLocationStatusHandler(
            $this->admissionRepository,
            $this->clock,
        );
    }

    public function testUpdatesLocationStatusToInConsultationRoom(): void
    {
        $now       = new \DateTimeImmutable('2026-05-08 10:00:00');
        $admission = $this->makeAdmission();

        $this->clock->expects(self::once())->method('now')->willReturn($now);
        $this->admissionRepository->expects(self::once())
            ->method('get')
            ->with(
                self::equalTo(ClinicId::fromString(self::CLINIC_ID)),
                self::equalTo(AdmissionId::fromString(self::ADMISSION_ID)),
            )
            ->willReturn($admission)
        ;
        $this->admissionRepository->expects(self::once())
            ->method('save')
            ->with(self::identicalTo($admission))
        ;

        ($this->handler)(new UpdateAdmissionLocationStatus(
            clinicId: self::CLINIC_ID,
            admissionId: self::ADMISSION_ID,
            newLocationStatus: 'in_consultation_room',
        ));

        self::assertSame(LocationStatusValue::InConsultationRoom, $admission->locationStatus()->value());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testThrowsWhenAdmissionNotFound(): void
    {
        $this->admissionRepository->method('get')
            ->willThrowException(new AdmissionNotFoundException(self::ADMISSION_ID))
        ;

        $this->expectException(\DomainException::class);

        ($this->handler)(new UpdateAdmissionLocationStatus(
            clinicId: self::CLINIC_ID,
            admissionId: self::ADMISSION_ID,
            newLocationStatus: 'in_consultation_room',
        ));
    }

    private function makeAdmission(): Admission
    {
        return Admission::open(
            id: AdmissionId::fromString(self::ADMISSION_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            patientId: self::PATIENT_ID,
            isPatientIdentified: true,
            channel: IntakeChannel::WalkIn,
            triage: TriageLevel::Standard,
            presenter: null,
            now: new \DateTimeImmutable('2026-05-08 09:00:00'),
        );
    }
}
