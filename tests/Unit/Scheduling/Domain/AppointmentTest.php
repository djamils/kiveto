<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduling\Domain;

use App\Scheduling\Domain\Appointment;
use App\Scheduling\Domain\Event\AppointmentCancelled;
use App\Scheduling\Domain\Event\AppointmentCompleted;
use App\Scheduling\Domain\Event\AppointmentMarkedNoShow;
use App\Scheduling\Domain\Event\AppointmentPractitionerAssigneeChanged;
use App\Scheduling\Domain\Event\AppointmentPractitionerAssigneeUnassigned;
use App\Scheduling\Domain\Event\AppointmentRescheduled;
use App\Scheduling\Domain\Event\AppointmentScheduled;
use App\Scheduling\Domain\Event\AppointmentServiceStarted;
use App\Scheduling\Domain\ValueObject\AnimalId;
use App\Scheduling\Domain\ValueObject\AppointmentId;
use App\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Scheduling\Domain\ValueObject\ClinicId;
use App\Scheduling\Domain\ValueObject\OwnerId;
use App\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use App\Scheduling\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class AppointmentTest extends TestCase
{
    public function testScheduleAppointmentWithValidData(): void
    {
        $appointmentId = AppointmentId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId      = ClinicId::fromString('11111111-1111-1111-1111-111111111111');
        $ownerId       = OwnerId::fromString('22222222-2222-2222-2222-222222222222');
        $animalId      = AnimalId::fromString('33333333-3333-3333-3333-333333333333');
        $practitioner  = new PractitionerAssignee(UserId::fromString('44444444-4444-4444-4444-444444444444'));
        $timeSlot      = new TimeSlot(new \DateTimeImmutable('2026-02-01 09:00:00'), 30);
        $createdAt     = new \DateTimeImmutable('2026-01-30 12:00:00');

        $appointment = Appointment::schedule(
            id: $appointmentId,
            clinicId: $clinicId,
            ownerId: $ownerId,
            animalId: $animalId,
            practitionerAssignee: $practitioner,
            timeSlot: $timeSlot,
            reason: 'Consultation',
            notes: 'First visit',
            createdAt: $createdAt,
        );

        self::assertTrue($appointment->id()->equals($appointmentId));
        self::assertTrue($appointment->clinicId()->equals($clinicId));
        self::assertSame(AppointmentStatus::PLANNED, $appointment->status());
        self::assertSame('Consultation', $appointment->reason());
        self::assertSame('First visit', $appointment->notes());

        $events = $appointment->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AppointmentScheduled::class, $events[0]);
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

        $assignee = $appointment->practitionerAssignee();
        self::assertNotNull($assignee);
        self::assertTrue($assignee->equals($newPractitioner));

        $events = $appointment->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AppointmentPractitionerAssigneeChanged::class, $events[0]);
    }

    public function testUnassignPractitioner(): void
    {
        $appointment  = $this->createSampleAppointment();
        $pulledEvents = $appointment->pullDomainEvents();
        unset($pulledEvents);

        $appointment->unassignPractitioner();

        self::assertNull($appointment->practitionerAssignee());

        $events = $appointment->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AppointmentPractitionerAssigneeUnassigned::class, $events[0]);
    }

    public function testUnassignPractitionerWhenNoneAssignedDoesNothing(): void
    {
        $appointment = Appointment::schedule(
            id: AppointmentId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('11111111-1111-1111-1111-111111111111'),
            ownerId: null,
            animalId: null,
            practitionerAssignee: null,
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-02-01 09:00:00'), 30),
            reason: null,
            notes: null,
            createdAt: new \DateTimeImmutable('2026-01-30 12:00:00'),
        );
        $pulledEvents = $appointment->pullDomainEvents();
        unset($pulledEvents);

        $appointment->unassignPractitioner();

        $events = $appointment->recordedDomainEvents();
        self::assertCount(0, $events);
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

    public function testCannotUnassignPractitionerOnTerminatedAppointment(): void
    {
        $appointment = $this->createSampleAppointment();
        $appointment->cancel();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot unassign practitioner from a terminated appointment.');

        $appointment->unassignPractitioner();
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

        $owner  = $appointment->ownerId();
        $animal = $appointment->animalId();
        self::assertNotNull($owner);
        self::assertNotNull($animal);
        self::assertTrue($owner->equals(OwnerId::fromString('22222222-2222-2222-2222-222222222222')));
        self::assertTrue($animal->equals(AnimalId::fromString('33333333-3333-3333-3333-333333333333')));
        self::assertSame('2026-01-30 12:00:00', $appointment->createdAt()->format('Y-m-d H:i:s'));
    }

    public function testReconstituteCreatesInstanceWithoutEvents(): void
    {
        $appointmentId = AppointmentId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $clinicId      = ClinicId::fromString('11111111-1111-1111-1111-111111111111');
        $timeSlot      = new TimeSlot(new \DateTimeImmutable('2026-02-01 09:00:00'), 30);
        $createdAt     = new \DateTimeImmutable('2026-01-30 12:00:00');

        $appointment = Appointment::reconstitute(
            id: $appointmentId,
            clinicId: $clinicId,
            ownerId: null,
            animalId: null,
            practitionerAssignee: null,
            timeSlot: $timeSlot,
            status: AppointmentStatus::COMPLETED,
            reason: null,
            notes: null,
            createdAt: $createdAt,
            serviceStartedAt: null,
        );

        self::assertTrue($appointment->id()->equals($appointmentId));
        self::assertSame(AppointmentStatus::COMPLETED, $appointment->status());

        $events = $appointment->recordedDomainEvents();
        self::assertCount(0, $events);
    }

    private function createSampleAppointment(): Appointment
    {
        return Appointment::schedule(
            id: AppointmentId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('11111111-1111-1111-1111-111111111111'),
            ownerId: OwnerId::fromString('22222222-2222-2222-2222-222222222222'),
            animalId: AnimalId::fromString('33333333-3333-3333-3333-333333333333'),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString('44444444-4444-4444-4444-444444444444')),
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-02-01 09:00:00'), 30),
            reason: 'Consultation',
            notes: null,
            createdAt: new \DateTimeImmutable('2026-01-30 12:00:00'),
        );
    }
}
