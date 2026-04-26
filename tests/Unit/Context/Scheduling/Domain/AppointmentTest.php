<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain;

use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Event\AppointmentCancelled;
use App\Context\Scheduling\Domain\Event\AppointmentCompleted;
use App\Context\Scheduling\Domain\Event\AppointmentMarkedNoShow;
use App\Context\Scheduling\Domain\Event\AppointmentPractitionerAssigneeChanged;
use App\Context\Scheduling\Domain\Event\AppointmentRescheduled;
use App\Context\Scheduling\Domain\Event\AppointmentScheduled;
use App\Context\Scheduling\Domain\Event\AppointmentServiceStarted;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class AppointmentTest extends TestCase
{
    public function testScheduleAppointmentWithValidData(): void
    {
        $appointmentId     = AppointmentId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId          = ClinicId::fromString('11111111-1111-1111-1111-111111111111');
        $linkedAdmissionId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $practitioner      = new PractitionerAssignee(UserId::fromString('44444444-4444-4444-4444-444444444444'));
        $timeSlot          = new TimeSlot(new \DateTimeImmutable('2026-02-01 09:00:00'), 30);
        $createdAt         = new \DateTimeImmutable('2026-01-30 12:00:00');

        $appointment = Appointment::schedule(
            id: $appointmentId,
            clinicId: $clinicId,
            practitionerAssignee: $practitioner,
            timeSlot: $timeSlot,
            reason: 'Consultation',
            notes: 'First visit',
            createdAt: $createdAt,
            linkedAdmissionId: $linkedAdmissionId,
        );

        self::assertTrue($appointment->id()->equals($appointmentId));
        self::assertTrue($appointment->clinicId()->equals($clinicId));
        self::assertSame(AppointmentStatus::PLANNED, $appointment->status());
        self::assertSame('Consultation', $appointment->reason());
        self::assertSame('First visit', $appointment->notes());
        self::assertSame($linkedAdmissionId, $appointment->linkedAdmissionId());

        $events = $appointment->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AppointmentScheduled::class, $events[0]);
    }

    public function testScheduleAppointmentWithoutLinkedAdmission(): void
    {
        $appointment = $this->createSampleAppointment();

        self::assertNull($appointment->linkedAdmissionId());
    }

    public function testRescheduleAppointment(): void
    {
        $appointment  = $this->createSampleAppointment();
        $pulledEvents = $appointment->pullDomainEvents();
        unset($pulledEvents);

        $newTimeSlot = new TimeSlot(new \DateTimeImmutable('2026-02-01 14:00:00'), 45);
        $appointment->reschedule($newTimeSlot);

        self::assertSame($newTimeSlot, $appointment->timeSlot());

        $events = $appointment->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AppointmentRescheduled::class, $events[0]);
    }

    public function testRescheduleWithSameTimeSlotDoesNothing(): void
    {
        $appointment  = $this->createSampleAppointment();
        $pulledEvents = $appointment->pullDomainEvents();
        unset($pulledEvents);

        $sameTimeSlot = new TimeSlot(new \DateTimeImmutable('2026-02-01 09:00:00'), 30);
        $appointment->reschedule($sameTimeSlot);

        $events = $appointment->recordedDomainEvents();
        self::assertCount(0, $events);
    }

    public function testCannotRescheduleTerminalAppointment(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot reschedule a terminated appointment.');

        $appointment = $this->createSampleAppointment();
        $appointment->cancel();

        $newTimeSlot = new TimeSlot(new \DateTimeImmutable('2026-02-01 14:00:00'), 30);
        $appointment->reschedule($newTimeSlot);
    }

    public function testChangePractitionerAssignee(): void
    {
        $appointment  = $this->createSampleAppointment();
        $pulledEvents = $appointment->pullDomainEvents();
        unset($pulledEvents);

        $newPractitioner = new PractitionerAssignee(UserId::fromString('55555555-5555-5555-5555-555555555555'));
        $appointment->changePractitionerAssignee($newPractitioner);

        self::assertTrue($appointment->practitionerAssignee()->equals($newPractitioner));

        $events = $appointment->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AppointmentPractitionerAssigneeChanged::class, $events[0]);
    }

    public function testCancelAppointment(): void
    {
        $appointment  = $this->createSampleAppointment();
        $pulledEvents = $appointment->pullDomainEvents();
        unset($pulledEvents);

        $appointment->cancel();

        self::assertSame(AppointmentStatus::CANCELLED, $appointment->status());

        $events = $appointment->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AppointmentCancelled::class, $events[0]);
    }

    public function testMarkNoShow(): void
    {
        $appointment  = $this->createSampleAppointment();
        $pulledEvents = $appointment->pullDomainEvents();
        unset($pulledEvents);

        $appointment->markNoShow();

        self::assertSame(AppointmentStatus::NO_SHOW, $appointment->status());

        $events = $appointment->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AppointmentMarkedNoShow::class, $events[0]);
    }

    public function testCompleteAppointment(): void
    {
        $appointment  = $this->createSampleAppointment();
        $pulledEvents = $appointment->pullDomainEvents();
        unset($pulledEvents);

        $appointment->complete();

        self::assertSame(AppointmentStatus::COMPLETED, $appointment->status());

        $events = $appointment->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AppointmentCompleted::class, $events[0]);
    }

    public function testStartService(): void
    {
        $appointment  = $this->createSampleAppointment();
        $pulledEvents = $appointment->pullDomainEvents();
        unset($pulledEvents);

        $serviceStartedAt = new \DateTimeImmutable('2026-02-01 09:05:00');
        $appointment->startService($serviceStartedAt);

        self::assertSame($serviceStartedAt, $appointment->serviceStartedAt());

        $events = $appointment->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AppointmentServiceStarted::class, $events[0]);
    }

    public function testStartServiceTwiceDoesNothing(): void
    {
        $appointment      = $this->createSampleAppointment();
        $serviceStartedAt = new \DateTimeImmutable('2026-02-01 09:05:00');
        $appointment->startService($serviceStartedAt);
        $pulledEvents = $appointment->pullDomainEvents();
        unset($pulledEvents);

        $appointment->startService(new \DateTimeImmutable('2026-02-01 09:10:00'));

        $events = $appointment->recordedDomainEvents();
        self::assertCount(0, $events);
    }

    public function testCannotChangePractitionerOnTerminatedAppointment(): void
    {
        $appointment = $this->createSampleAppointment();
        $appointment->cancel();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot change practitioner for a terminated appointment.');

        $appointment->changePractitionerAssignee(
            new PractitionerAssignee(UserId::fromString('99999999-9999-9999-9999-999999999999')),
        );
    }

    public function testCannotStartServiceOnTerminatedAppointment(): void
    {
        $appointment = $this->createSampleAppointment();
        $appointment->cancel();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot start service for a terminated appointment.');

        $appointment->startService(new \DateTimeImmutable('2026-04-10 09:05:00'));
    }

    public function testChangePractitionerAssigneeWithSameAssigneeIsIdempotent(): void
    {
        $appointment = $this->createSampleAppointment();
        $pulled      = $appointment->pullDomainEvents();
        unset($pulled);

        $sameAssignee = new PractitionerAssignee(
            UserId::fromString('44444444-4444-4444-4444-444444444444'),
        );
        $appointment->changePractitionerAssignee($sameAssignee);

        self::assertCount(0, $appointment->recordedDomainEvents());
    }

    public function testCancelTwiceIsIdempotent(): void
    {
        $appointment = $this->createSampleAppointment();
        $appointment->cancel();
        $pulled = $appointment->pullDomainEvents();
        unset($pulled);

        $appointment->cancel();
        self::assertCount(0, $appointment->recordedDomainEvents());
    }

    public function testCannotCancelCompletedAppointment(): void
    {
        $appointment = $this->createSampleAppointment();
        $appointment->complete();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot cancel an appointment that is already terminated.');

        $appointment->cancel();
    }

    public function testMarkNoShowTwiceIsIdempotent(): void
    {
        $appointment = $this->createSampleAppointment();
        $appointment->markNoShow();
        $pulled = $appointment->pullDomainEvents();
        unset($pulled);

        $appointment->markNoShow();
        self::assertCount(0, $appointment->recordedDomainEvents());
    }

    public function testCannotMarkNoShowOnCompletedAppointment(): void
    {
        $appointment = $this->createSampleAppointment();
        $appointment->complete();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot mark as no-show an appointment that is already terminated.');

        $appointment->markNoShow();
    }

    public function testCompleteTwiceIsIdempotent(): void
    {
        $appointment = $this->createSampleAppointment();
        $appointment->complete();
        $pulled = $appointment->pullDomainEvents();
        unset($pulled);

        $appointment->complete();
        self::assertCount(0, $appointment->recordedDomainEvents());
    }

    public function testCannotCompleteCancelledAppointment(): void
    {
        $appointment = $this->createSampleAppointment();
        $appointment->cancel();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot complete an appointment that is already terminated.');

        $appointment->complete();
    }

    public function testGettersExposeAggregateState(): void
    {
        $appointment = $this->createSampleAppointment();

        self::assertNull($appointment->linkedAdmissionId());
        self::assertSame('2026-01-30 12:00:00', $appointment->createdAt()->format('Y-m-d H:i:s'));
    }

    public function testReconstituteCreatesInstanceWithoutEvents(): void
    {
        $appointmentId = AppointmentId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId      = ClinicId::fromString('11111111-1111-1111-1111-111111111111');
        $practitioner  = new PractitionerAssignee(UserId::fromString('44444444-4444-4444-4444-444444444444'));
        $timeSlot      = new TimeSlot(new \DateTimeImmutable('2026-02-01 09:00:00'), 30);
        $createdAt     = new \DateTimeImmutable('2026-01-30 12:00:00');

        $appointment = Appointment::reconstitute(
            id: $appointmentId,
            clinicId: $clinicId,
            practitionerAssignee: $practitioner,
            timeSlot: $timeSlot,
            status: AppointmentStatus::COMPLETED,
            reason: null,
            notes: null,
            createdAt: $createdAt,
            serviceStartedAt: null,
            linkedAdmissionId: null,
        );

        self::assertTrue($appointment->id()->equals($appointmentId));
        self::assertSame(AppointmentStatus::COMPLETED, $appointment->status());
        self::assertNull($appointment->linkedAdmissionId());

        $events = $appointment->recordedDomainEvents();
        self::assertCount(0, $events);
    }

    public function testReconstituteWithLinkedAdmissionId(): void
    {
        $admissionId = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';

        $appointment = Appointment::reconstitute(
            id: AppointmentId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('11111111-1111-1111-1111-111111111111'),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString('44444444-4444-4444-4444-444444444444')),
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-02-01 09:00:00'), 30),
            status: AppointmentStatus::PLANNED,
            reason: null,
            notes: null,
            createdAt: new \DateTimeImmutable('2026-01-30 12:00:00'),
            serviceStartedAt: null,
            linkedAdmissionId: $admissionId,
        );

        self::assertSame($admissionId, $appointment->linkedAdmissionId());
    }

    private function createSampleAppointment(): Appointment
    {
        return Appointment::schedule(
            id: AppointmentId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('11111111-1111-1111-1111-111111111111'),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString('44444444-4444-4444-4444-444444444444')),
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-02-01 09:00:00'), 30),
            reason: 'Consultation',
            notes: null,
            createdAt: new \DateTimeImmutable('2026-01-30 12:00:00'),
        );
    }
}
