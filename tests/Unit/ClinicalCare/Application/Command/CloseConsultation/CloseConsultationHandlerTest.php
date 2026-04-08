<?php

declare(strict_types=1);

namespace App\Tests\Unit\ClinicalCare\Application\Command\CloseConsultation;

use App\ClinicalCare\Application\Command\CloseConsultation\CloseConsultation;
use App\ClinicalCare\Application\Command\CloseConsultation\CloseConsultationHandler;
use App\ClinicalCare\Application\Port\SchedulingServiceCoordinatorInterface;
use App\ClinicalCare\Domain\Consultation;
use App\ClinicalCare\Domain\Repository\ConsultationRepositoryInterface;
use App\ClinicalCare\Domain\ValueObject\AppointmentId;
use App\ClinicalCare\Domain\ValueObject\ClinicId;
use App\ClinicalCare\Domain\ValueObject\ConsultationId;
use App\ClinicalCare\Domain\ValueObject\ConsultationStatus;
use App\ClinicalCare\Domain\ValueObject\UserId;
use App\ClinicalCare\Domain\ValueObject\WaitingRoomEntryId;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CloseConsultationHandlerTest extends TestCase
{
    private const string CONSULTATION_ID = '11111111-1111-4111-8111-111111111111';
    private const string CLINIC_ID       = '22222222-2222-4222-8222-222222222222';
    private const string APPOINTMENT_ID  = '33333333-3333-4333-8333-333333333333';
    private const string ENTRY_ID        = '44444444-4444-4444-8444-444444444444';
    private const string USER_ID         = '55555555-5555-4555-8555-555555555555';

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

    public function testCloseConsultationFromWaitingRoomEntryDoesNotCompleteAppointment(): void
    {
        $consultation = $this->makeWaitingRoomConsultation();
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
            UserId::fromString(self::USER_ID),
            null,
            null,
            new \DateTimeImmutable('2026-04-10 09:00:00'),
        );
    }

    private function makeWaitingRoomConsultation(): Consultation
    {
        return Consultation::startFromWaitingRoomEntry(
            ConsultationId::fromString(self::CONSULTATION_ID),
            ClinicId::fromString(self::CLINIC_ID),
            WaitingRoomEntryId::fromString(self::ENTRY_ID),
            UserId::fromString(self::USER_ID),
            null,
            null,
            new \DateTimeImmutable('2026-04-10 09:00:00'),
        );
    }
}
