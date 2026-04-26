<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Command\StartConsultationFromAppointment;

use App\Context\Consultation\Application\Command\StartConsultationFromAppointment\StartConsultationFromAppointment;
use App\Context\Consultation\Application\Command\StartConsultationFromAppointment\StartConsultationFromAppointmentHandler as StartFromAppointmentHandler; // phpcs:ignore Generic.Files.LineLength.TooLong
use App\Context\Consultation\Application\Port\AdmissionContextDto;
use App\Context\Consultation\Application\Port\AdmissionContextProviderInterface;
use App\Context\Consultation\Application\Port\AppointmentContextDTO;
use App\Context\Consultation\Application\Port\PractitionerEligibilityCheckerInterface;
use App\Context\Consultation\Application\Port\SchedulingAppointmentContextProviderInterface;
use App\Context\Consultation\Application\Port\SchedulingServiceCoordinatorInterface;
use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StartConsultationFromAppointmentHandlerTest extends TestCase
{
    private const string APPOINTMENT_ID = '33333333-3333-4333-8333-333333333333';
    private const string CLINIC_ID      = '22222222-2222-4222-8222-222222222222';
    private const string ADMISSION_ID   = '44444444-4444-4444-8444-444444444444';
    private const string PATIENT_ID     = '66666666-6666-4666-8666-666666666666';
    private const string USER_ID        = '55555555-5555-4555-8555-555555555555';

    private ConsultationRepositoryInterface&MockObject $consultations;
    private PractitionerEligibilityCheckerInterface&MockObject $eligibility;
    private SchedulingAppointmentContextProviderInterface&MockObject $contextProvider;
    private SchedulingServiceCoordinatorInterface&MockObject $coordinator;
    private AdmissionContextProviderInterface&MockObject $admissionContextProvider;
    private ClockInterface&MockObject $clock;
    private StartFromAppointmentHandler $handler;

    protected function setUp(): void
    {
        $this->consultations            = $this->createMock(ConsultationRepositoryInterface::class);
        $this->eligibility              = $this->createMock(PractitionerEligibilityCheckerInterface::class);
        $this->contextProvider          = $this->createMock(SchedulingAppointmentContextProviderInterface::class);
        $this->coordinator              = $this->createMock(SchedulingServiceCoordinatorInterface::class);
        $this->admissionContextProvider = $this->createMock(AdmissionContextProviderInterface::class);
        $this->clock                    = $this->createMock(ClockInterface::class);

        $this->handler = new StartFromAppointmentHandler(
            $this->consultations,
            $this->eligibility,
            $this->contextProvider,
            $this->coordinator,
            $this->admissionContextProvider,
            $this->clock,
        );
    }

    public function testStartConsultationSuccessfully(): void
    {
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:00:00'));
        $this->contextProvider->expects(self::once())
            ->method('getAppointmentContext')
            ->willReturn(new AppointmentContextDTO(
                clinicId: self::CLINIC_ID,
                admissionId: self::ADMISSION_ID,
                status: 'PLANNED',
            ))
        ;
        $this->admissionContextProvider->expects(self::once())
            ->method('getAdmissionContext')
            ->with(self::ADMISSION_ID)
            ->willReturn(new AdmissionContextDto(
                patientId: self::PATIENT_ID,
                clinicId: self::CLINIC_ID,
            ))
        ;
        $this->eligibility->expects(self::once())->method('isEligibleForClinicAt')->willReturn(true);
        $this->coordinator->expects(self::once())->method('ensureAppointmentInService');
        $this->consultations->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(Consultation::class))
        ;

        $consultationId = ($this->handler)(new StartConsultationFromAppointment(
            appointmentId: self::APPOINTMENT_ID,
            startedByUserId: self::USER_ID,
        ));

        self::assertNotEmpty($consultationId);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenPractitionerNotEligible(): void
    {
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:00:00'));
        $this->contextProvider->expects(self::once())
            ->method('getAppointmentContext')
            ->willReturn(new AppointmentContextDTO(
                clinicId: self::CLINIC_ID,
                admissionId: self::ADMISSION_ID,
                status: 'PLANNED',
            ))
        ;
        $this->admissionContextProvider->expects(self::never())->method('getAdmissionContext');
        $this->eligibility->expects(self::once())->method('isEligibleForClinicAt')->willReturn(false);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('User is not eligible as practitioner for this clinic');

        ($this->handler)(new StartConsultationFromAppointment(
            appointmentId: self::APPOINTMENT_ID,
            startedByUserId: self::USER_ID,
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenAppointmentNotCheckedIn(): void
    {
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:00:00'));
        $this->contextProvider->expects(self::once())
            ->method('getAppointmentContext')
            ->willReturn(new AppointmentContextDTO(
                clinicId: self::CLINIC_ID,
                admissionId: null,
                status: 'PLANNED',
            ))
        ;
        $this->eligibility->expects(self::once())->method('isEligibleForClinicAt')->willReturn(true);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Appointment must be checked-in');

        ($this->handler)(new StartConsultationFromAppointment(
            appointmentId: self::APPOINTMENT_ID,
            startedByUserId: self::USER_ID,
        ));
    }
}
