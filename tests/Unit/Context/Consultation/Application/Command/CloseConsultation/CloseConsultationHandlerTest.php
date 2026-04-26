<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Application\Command\CloseConsultation;

use App\Context\Consultation\Application\Command\CloseConsultation\CloseConsultation;
use App\Context\Consultation\Application\Command\CloseConsultation\CloseConsultationHandler;
use App\Context\Consultation\Application\Port\SchedulingServiceCoordinatorInterface;
use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\AppointmentId;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\ConsultationStatus;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CloseConsultationHandlerTest extends TestCase
{
    private const string CONSULTATION_ID = '11111111-1111-4111-8111-111111111111';
    private const string CLINIC_ID       = '22222222-2222-4222-8222-222222222222';
    private const string APPOINTMENT_ID  = '33333333-3333-4333-8333-333333333333';
    private const string ADMISSION_ID    = '44444444-4444-4444-8444-444444444444';
    private const string USER_ID         = '55555555-5555-4555-8555-555555555555';
    private const string PATIENT_ID      = '66666666-6666-4666-8666-666666666666';

    private ConsultationRepositoryInterface&MockObject $consultations;
    private SchedulingServiceCoordinatorInterface&MockObject $coordinator;
    private ClockInterface&MockObject $clock;
    private CloseConsultationHandler $handler;

    protected function setUp(): void
    {
        $this->consultations = $this->createMock(ConsultationRepositoryInterface::class);
        $this->coordinator   = $this->createMock(SchedulingServiceCoordinatorInterface::class);
        $this->clock         = $this->createMock(ClockInterface::class);

        $this->handler = new CloseConsultationHandler(
            $this->consultations,
            $this->coordinator,
            $this->clock,
        );
    }

    public function testCloseConsultationFromAppointmentCompletesAppointment(): void
    {
        $consultation = $this->makeAppointmentConsultation();
        $this->consultations->expects(self::once())->method('findById')->willReturn($consultation);
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 10:00:00'));
        $this->consultations->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (Consultation $c): bool => ConsultationStatus::CLOSED === $c->getStatus()))
        ;
        $this->coordinator->expects(self::once())
            ->method('completeAppointment')
            ->with(
                self::callback(static fn (AppointmentId $id): bool => self::APPOINTMENT_ID === $id->toString()),
                self::callback(static fn (UserId $id): bool => self::USER_ID === $id->toString()),
            )
        ;

        ($this->handler)(new CloseConsultation(
            consultationId: self::CONSULTATION_ID,
            closedByUserId: self::USER_ID,
            summary: 'Done',
        ));
    }

    public function testCloseConsultationFromAdmissionDoesNotCompleteAppointment(): void
    {
        $consultation = $this->makeAdmissionConsultation();
        $this->consultations->expects(self::once())->method('findById')->willReturn($consultation);
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 10:00:00'));
        $this->consultations->expects(self::once())->method('save');
        $this->coordinator->expects(self::never())->method('completeAppointment');

        ($this->handler)(new CloseConsultation(
            consultationId: self::CONSULTATION_ID,
            closedByUserId: self::USER_ID,
            summary: null,
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenConsultationNotFound(): void
    {
        $this->consultations->expects(self::once())->method('findById')->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Consultation not found');

        ($this->handler)(new CloseConsultation(
            consultationId: self::CONSULTATION_ID,
            closedByUserId: self::USER_ID,
            summary: null,
        ));
    }

    private function makeAppointmentConsultation(): Consultation
    {
        return Consultation::startFromAppointment(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            AppointmentId::fromString(self::APPOINTMENT_ID),
            AdmissionId::fromString(self::ADMISSION_ID),
            PatientId::fromString(self::PATIENT_ID),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 09:00:00'),
        );
    }

    private function makeAdmissionConsultation(): Consultation
    {
        return Consultation::startFromAdmission(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            AdmissionId::fromString(self::ADMISSION_ID),
            PatientId::fromString(self::PATIENT_ID),
            UserId::fromString(self::USER_ID),
            new \DateTimeImmutable('2026-04-10 09:00:00'),
        );
    }
}
