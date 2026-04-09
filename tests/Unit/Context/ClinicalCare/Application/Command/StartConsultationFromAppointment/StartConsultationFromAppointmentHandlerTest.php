<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClinicalCare\Application\Command\StartConsultationFromAppointment;

use App\Context\ClinicalCare\Application\Command\StartConsultationFromAppointment\StartConsultationFromAppointment;
use App\Context\ClinicalCare\Application\Command\StartConsultationFromAppointment\StartConsultationFromAppointmentHandler as StartFromAppointmentHandler; // phpcs:ignore Generic.Files.LineLength.TooLong
use App\Context\ClinicalCare\Application\Port\AppointmentContextDTO;
use App\Context\ClinicalCare\Application\Port\PractitionerEligibilityCheckerInterface;
use App\Context\ClinicalCare\Application\Port\SchedulingAppointmentContextProviderInterface;
use App\Context\ClinicalCare\Application\Port\SchedulingServiceCoordinatorInterface;
use App\Context\ClinicalCare\Domain\Consultation;
use App\Context\ClinicalCare\Domain\Repository\ConsultationRepositoryInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StartConsultationFromAppointmentHandlerTest extends TestCase
{
    private const string APPOINTMENT_ID = '33333333-3333-4333-8333-333333333333';
    private const string CLINIC_ID      = '22222222-2222-4222-8222-222222222222';
    private const string ENTRY_ID       = '44444444-4444-4444-8444-444444444444';
    private const string USER_ID        = '55555555-5555-4555-8555-555555555555';

    private ConsultationRepositoryInterface&MockObject $consultations;
    private PractitionerEligibilityCheckerInterface&MockObject $eligibility;
    private SchedulingAppointmentContextProviderInterface&MockObject $contextProvider;
    private SchedulingServiceCoordinatorInterface&MockObject $coordinator;
    private ClockInterface&MockObject $clock;
    private StartFromAppointmentHandler $handler;

    protected function setUp(): void
    {
        $this->consultations   = $this->createMock(ConsultationRepositoryInterface::class);
        $this->eligibility     = $this->createMock(PractitionerEligibilityCheckerInterface::class);
        $this->contextProvider = $this->createMock(SchedulingAppointmentContextProviderInterface::class);
        $this->coordinator     = $this->createMock(SchedulingServiceCoordinatorInterface::class);
        $this->clock           = $this->createMock(ClockInterface::class);

        $this->handler = new StartFromAppointmentHandler(
            $this->consultations,
            $this->eligibility,
            $this->contextProvider,
            $this->coordinator,
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
                linkedWaitingRoomEntryId: self::ENTRY_ID,
                ownerId: '66666666-6666-4666-8666-666666666666',
                animalId: '77777777-7777-4777-8777-777777777777',
                arrivalMode: 'STANDARD',
                status: 'PLANNED',
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

    public function testEmergencyAppointmentWithoutWaitingRoomEntryIsAllowed(): void
    {
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:00:00'));
        $this->contextProvider->expects(self::once())
            ->method('getAppointmentContext')
            ->willReturn(new AppointmentContextDTO(
                clinicId: self::CLINIC_ID,
                linkedWaitingRoomEntryId: null,
                ownerId: null,
                animalId: null,
                arrivalMode: 'EMERGENCY',
                status: 'PLANNED',
            ))
        ;
        $this->eligibility->expects(self::once())->method('isEligibleForClinicAt')->willReturn(true);
        $this->coordinator->expects(self::once())->method('ensureAppointmentInService');
        $this->consultations->expects(self::once())->method('save');

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
                linkedWaitingRoomEntryId: self::ENTRY_ID,
                ownerId: null,
                animalId: null,
                arrivalMode: 'STANDARD',
                status: 'PLANNED',
            ))
        ;
        $this->eligibility->expects(self::once())->method('isEligibleForClinicAt')->willReturn(false);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('User is not eligible as practitioner for this clinic');

        ($this->handler)(new StartConsultationFromAppointment(
            appointmentId: self::APPOINTMENT_ID,
            startedByUserId: self::USER_ID,
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenStandardAppointmentNotCheckedIn(): void
    {
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 09:00:00'));
        $this->contextProvider->expects(self::once())
            ->method('getAppointmentContext')
            ->willReturn(new AppointmentContextDTO(
                clinicId: self::CLINIC_ID,
                linkedWaitingRoomEntryId: null,
                ownerId: null,
                animalId: null,
                arrivalMode: 'STANDARD',
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
