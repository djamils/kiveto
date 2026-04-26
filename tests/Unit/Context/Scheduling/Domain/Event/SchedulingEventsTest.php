<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain\Event;

use App\Context\Scheduling\Domain\Event\AppointmentCancelled;
use App\Context\Scheduling\Domain\Event\AppointmentCompleted;
use App\Context\Scheduling\Domain\Event\AppointmentMarkedNoShow;
use App\Context\Scheduling\Domain\Event\AppointmentPractitionerAssigneeChanged;
use App\Context\Scheduling\Domain\Event\AppointmentRescheduled;
use App\Context\Scheduling\Domain\Event\AppointmentScheduled;
use App\Context\Scheduling\Domain\Event\AppointmentServiceStarted;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that every Scheduling domain event exposes the right
 * aggregateId, payload shape and event name.
 */
final class SchedulingEventsTest extends TestCase
{
    private const string APPOINTMENT_ID = '11111111-1111-1111-1111-111111111111';
    private const string CLINIC_ID      = '22222222-2222-2222-2222-222222222222';
    private const string ADMISSION_ID   = '33333333-3333-3333-3333-333333333333';
    private const string USER_ID        = '55555555-5555-5555-5555-555555555555';
    private const string OTHER_USER_ID  = '66666666-6666-6666-6666-666666666666';

    public function testAppointmentScheduled(): void
    {
        $event = new AppointmentScheduled(
            self::APPOINTMENT_ID,
            self::CLINIC_ID,
            self::ADMISSION_ID,
            self::USER_ID,
            '2026-04-10T09:00:00+00:00',
            30,
            'Routine checkup',
            'Anxious patient',
        );

        self::assertSame(self::APPOINTMENT_ID, $event->aggregateId());
        self::assertSame('scheduling.appointment.scheduled.v1', $event->name());
        self::assertSame([
            'appointmentId'      => self::APPOINTMENT_ID,
            'clinicId'           => self::CLINIC_ID,
            'linkedAdmissionId'  => self::ADMISSION_ID,
            'practitionerUserId' => self::USER_ID,
            'startsAtUtc'        => '2026-04-10T09:00:00+00:00',
            'durationMinutes'    => 30,
            'reason'             => 'Routine checkup',
            'notes'              => 'Anxious patient',
        ], $event->payload());
    }

    public function testAppointmentScheduledWithNullableFieldsOmitted(): void
    {
        $event = new AppointmentScheduled(
            self::APPOINTMENT_ID,
            self::CLINIC_ID,
            null,
            self::USER_ID,
            '2026-04-10T09:00:00+00:00',
            15,
            null,
            null,
        );

        $payload = $event->payload();
        self::assertNull($payload['linkedAdmissionId']);
        self::assertSame(self::USER_ID, $payload['practitionerUserId']);
        self::assertNull($payload['reason']);
        self::assertNull($payload['notes']);
    }

    public function testAppointmentCancelled(): void
    {
        $event = new AppointmentCancelled(self::APPOINTMENT_ID, self::CLINIC_ID);

        self::assertSame(self::APPOINTMENT_ID, $event->aggregateId());
        self::assertSame('scheduling.appointment.cancelled.v1', $event->name());
        self::assertSame([
            'appointmentId' => self::APPOINTMENT_ID,
            'clinicId'      => self::CLINIC_ID,
        ], $event->payload());
    }

    public function testAppointmentCompleted(): void
    {
        $event = new AppointmentCompleted(self::APPOINTMENT_ID, self::CLINIC_ID);

        self::assertSame(self::APPOINTMENT_ID, $event->aggregateId());
        self::assertSame('scheduling.appointment.completed.v1', $event->name());
        self::assertSame([
            'appointmentId' => self::APPOINTMENT_ID,
            'clinicId'      => self::CLINIC_ID,
        ], $event->payload());
    }

    public function testAppointmentMarkedNoShow(): void
    {
        $event = new AppointmentMarkedNoShow(self::APPOINTMENT_ID, self::CLINIC_ID);

        self::assertSame(self::APPOINTMENT_ID, $event->aggregateId());
        self::assertSame('scheduling.appointment-marked-no.show.v1', $event->name());
        self::assertSame([
            'appointmentId' => self::APPOINTMENT_ID,
            'clinicId'      => self::CLINIC_ID,
        ], $event->payload());
    }

    public function testAppointmentPractitionerAssigneeChanged(): void
    {
        $event = new AppointmentPractitionerAssigneeChanged(
            self::APPOINTMENT_ID,
            self::CLINIC_ID,
            self::USER_ID,
            self::OTHER_USER_ID,
        );

        self::assertSame(self::APPOINTMENT_ID, $event->aggregateId());
        self::assertSame([
            'appointmentId'         => self::APPOINTMENT_ID,
            'clinicId'              => self::CLINIC_ID,
            'oldPractitionerUserId' => self::USER_ID,
            'newPractitionerUserId' => self::OTHER_USER_ID,
        ], $event->payload());
    }

    public function testAppointmentPractitionerAssigneeChangedAcceptsNullPrevious(): void
    {
        $event = new AppointmentPractitionerAssigneeChanged(
            self::APPOINTMENT_ID,
            self::CLINIC_ID,
            null,
            self::USER_ID,
        );

        self::assertNull($event->payload()['oldPractitionerUserId']);
    }

    public function testAppointmentRescheduled(): void
    {
        $event = new AppointmentRescheduled(
            self::APPOINTMENT_ID,
            self::CLINIC_ID,
            '2026-04-10T09:00:00+00:00',
            30,
            '2026-04-11T14:00:00+00:00',
            45,
        );

        self::assertSame(self::APPOINTMENT_ID, $event->aggregateId());
        self::assertSame([
            'appointmentId'      => self::APPOINTMENT_ID,
            'clinicId'           => self::CLINIC_ID,
            'oldStartsAtUtc'     => '2026-04-10T09:00:00+00:00',
            'oldDurationMinutes' => 30,
            'newStartsAtUtc'     => '2026-04-11T14:00:00+00:00',
            'newDurationMinutes' => 45,
        ], $event->payload());
    }

    public function testAppointmentServiceStarted(): void
    {
        $event = new AppointmentServiceStarted(
            self::APPOINTMENT_ID,
            self::CLINIC_ID,
            '2026-04-10T09:05:00+00:00',
        );

        self::assertSame(self::APPOINTMENT_ID, $event->aggregateId());
        self::assertSame([
            'appointmentId'       => self::APPOINTMENT_ID,
            'clinicId'            => self::CLINIC_ID,
            'serviceStartedAtUtc' => '2026-04-10T09:05:00+00:00',
        ], $event->payload());
    }
}
