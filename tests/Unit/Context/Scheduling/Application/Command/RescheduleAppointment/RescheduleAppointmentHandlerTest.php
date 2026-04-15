<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\RescheduleAppointment;

use App\Context\Scheduling\Application\Command\RescheduleAppointment\RescheduleAppointment;
use App\Context\Scheduling\Application\Command\RescheduleAppointment\RescheduleAppointmentHandler;
use App\Context\Scheduling\Application\Port\AppointmentConflictCheckerInterface;
use App\Context\Scheduling\Application\Port\MembershipEligibilityCheckerInterface;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\OwnerId;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RescheduleAppointmentHandlerTest extends TestCase
{
    private const string CLINIC_ID      = '11111111-1111-1111-1111-111111111111';
    private const string APPOINTMENT_ID = '22222222-2222-2222-2222-222222222222';
    private const string PRACTITIONER   = '33333333-3333-3333-3333-333333333333';
    private const string OTHER_PRACT    = '44444444-4444-4444-4444-444444444444';

    private AppointmentRepositoryInterface&MockObject $repository;
    private AppointmentConflictCheckerInterface&MockObject $conflictChecker;
    private MembershipEligibilityCheckerInterface&MockObject $eligibilityChecker;
    private ClockInterface&MockObject $clock;
    private RescheduleAppointmentHandler $handler;

    protected function setUp(): void
    {
        $this->repository         = $this->createMock(AppointmentRepositoryInterface::class);
        $this->conflictChecker    = $this->createMock(AppointmentConflictCheckerInterface::class);
        $this->eligibilityChecker = $this->createMock(MembershipEligibilityCheckerInterface::class);
        $this->clock              = $this->createMock(ClockInterface::class);

        $this->handler = new RescheduleAppointmentHandler(
            appointmentRepository: $this->repository,
            conflictChecker: $this->conflictChecker,
            membershipEligibilityChecker: $this->eligibilityChecker,
            clock: $this->clock,
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRescheduleHappyPath(): void
    {
        $appointment = $this->createFutureAppointment();

        $this->repository->method('findById')->willReturn($appointment);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:00:00'));
        $this->eligibilityChecker->method('isUserEligibleForClinicAt')->willReturn(true);
        $this->conflictChecker->method('hasOverlap')->willReturn(false);
        $this->repository->expects(self::once())->method('save');

        ($this->handler)(new RescheduleAppointment(
            clinicId: self::CLINIC_ID,
            appointmentId: self::APPOINTMENT_ID,
            startsAtUtc: new \DateTimeImmutable('2026-04-12 14:00:00'),
            durationMinutes: 45,
            practitionerUserId: self::OTHER_PRACT,
        ));

        $events = $appointment->recordedDomainEvents();
        // AppointmentScheduled (from create) + AppointmentRescheduled + AppointmentPractitionerAssigneeChanged
        self::assertCount(3, $events);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRejectsConflict(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('overlapping');

        $appointment = $this->createFutureAppointment();
        $this->repository->method('findById')->willReturn($appointment);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:00:00'));
        $this->eligibilityChecker->method('isUserEligibleForClinicAt')->willReturn(true);
        $this->conflictChecker->method('hasOverlap')->willReturn(true);

        ($this->handler)(new RescheduleAppointment(
            clinicId: self::CLINIC_ID,
            appointmentId: self::APPOINTMENT_ID,
            startsAtUtc: new \DateTimeImmutable('2026-04-12 14:00:00'),
            durationMinutes: 30,
            practitionerUserId: self::PRACTITIONER,
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRejectsIneligiblePractitioner(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not eligible');

        $appointment = $this->createFutureAppointment();
        $this->repository->method('findById')->willReturn($appointment);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:00:00'));
        $this->eligibilityChecker->method('isUserEligibleForClinicAt')->willReturn(false);

        ($this->handler)(new RescheduleAppointment(
            clinicId: self::CLINIC_ID,
            appointmentId: self::APPOINTMENT_ID,
            startsAtUtc: new \DateTimeImmutable('2026-04-12 14:00:00'),
            durationMinutes: 30,
            practitionerUserId: self::PRACTITIONER,
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRejectsCancelledAppointment(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot reschedule a terminated appointment.');

        $appointment = $this->createFutureAppointment();
        $appointment->cancel();
        $this->repository->method('findById')->willReturn($appointment);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:00:00'));
        $this->eligibilityChecker->method('isUserEligibleForClinicAt')->willReturn(true);
        $this->conflictChecker->method('hasOverlap')->willReturn(false);

        ($this->handler)(new RescheduleAppointment(
            clinicId: self::CLINIC_ID,
            appointmentId: self::APPOINTMENT_ID,
            startsAtUtc: new \DateTimeImmutable('2026-04-12 14:00:00'),
            durationMinutes: 30,
            practitionerUserId: self::PRACTITIONER,
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRejectsNoShowAppointment(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot reschedule a terminated appointment.');

        $appointment = $this->createFutureAppointment();
        $appointment->markNoShow();
        $this->repository->method('findById')->willReturn($appointment);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:00:00'));
        $this->eligibilityChecker->method('isUserEligibleForClinicAt')->willReturn(true);
        $this->conflictChecker->method('hasOverlap')->willReturn(false);

        ($this->handler)(new RescheduleAppointment(
            clinicId: self::CLINIC_ID,
            appointmentId: self::APPOINTMENT_ID,
            startsAtUtc: new \DateTimeImmutable('2026-04-12 14:00:00'),
            durationMinutes: 30,
            practitionerUserId: self::PRACTITIONER,
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSameTimeSlotAndPractitionerIsNoOp(): void
    {
        $appointment = $this->createFutureAppointment();
        $pulled      = $appointment->pullDomainEvents();
        unset($pulled);

        $this->repository->method('findById')->willReturn($appointment);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:00:00'));
        $this->eligibilityChecker->method('isUserEligibleForClinicAt')->willReturn(true);
        $this->conflictChecker->method('hasOverlap')->willReturn(false);
        $this->repository->expects(self::once())->method('save');

        ($this->handler)(new RescheduleAppointment(
            clinicId: self::CLINIC_ID,
            appointmentId: self::APPOINTMENT_ID,
            startsAtUtc: new \DateTimeImmutable('2026-04-11 09:00:00'),
            durationMinutes: 30,
            practitionerUserId: self::PRACTITIONER,
        ));

        // No domain events recorded (both reschedule and changePractitioner are no-ops)
        self::assertCount(0, $appointment->recordedDomainEvents());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRejectsWrongClinic(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Rendez-vous introuvable.');

        $appointment = $this->createFutureAppointment();
        $this->repository->method('findById')->willReturn($appointment);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:00:00'));

        ($this->handler)(new RescheduleAppointment(
            clinicId: '99999999-9999-9999-9999-999999999999',
            appointmentId: self::APPOINTMENT_ID,
            startsAtUtc: new \DateTimeImmutable('2026-04-12 14:00:00'),
            durationMinutes: 30,
            practitionerUserId: self::PRACTITIONER,
        ));
    }

    private function createFutureAppointment(): Appointment
    {
        return Appointment::schedule(
            id: AppointmentId::fromString(self::APPOINTMENT_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            ownerId: OwnerId::fromString('55555555-5555-5555-5555-555555555555'),
            animalId: null,
            practitionerAssignee: new PractitionerAssignee(UserId::fromString(self::PRACTITIONER)),
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-04-11 09:00:00'), 30),
            reason: 'Consultation',
            notes: null,
            createdAt: new \DateTimeImmutable('2026-04-09 12:00:00'),
        );
    }
}
