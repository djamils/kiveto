<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduling\Domain\Event;

use App\Scheduling\Domain\Event\AppointmentCancelled;
use App\Scheduling\Domain\Event\AppointmentCompleted;
use App\Scheduling\Domain\Event\AppointmentMarkedNoShow;
use App\Scheduling\Domain\Event\AppointmentPractitionerAssigneeChanged;
use App\Scheduling\Domain\Event\AppointmentPractitionerAssigneeUnassigned;
use App\Scheduling\Domain\Event\AppointmentRescheduled;
use App\Scheduling\Domain\Event\AppointmentScheduled;
use App\Scheduling\Domain\Event\AppointmentServiceStarted;
use App\Scheduling\Domain\Event\WaitingRoomEntryCalled;
use App\Scheduling\Domain\Event\WaitingRoomEntryClosed;
use App\Scheduling\Domain\Event\WaitingRoomEntryCreatedFromAppointment;
use App\Scheduling\Domain\Event\WaitingRoomEntryLinkedToOwnerAndAnimal;
use App\Scheduling\Domain\Event\WaitingRoomEntryServiceStarted;
use App\Scheduling\Domain\Event\WaitingRoomEntryTriageUpdated;
use App\Scheduling\Domain\Event\WaitingRoomWalkInEntryCreated;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that every Scheduling domain event exposes the right
 * aggregateId, payload shape and event name.
 */
final class SchedulingEventsTest extends TestCase
{
    private const string APPOINTMENT_ID = '11111111-1111-1111-1111-111111111111';
    private const string CLINIC_ID      = '22222222-2222-2222-2222-222222222222';
    private const string OWNER_ID       = '33333333-3333-3333-3333-333333333333';
    private const string ANIMAL_ID      = '44444444-4444-4444-4444-444444444444';
    private const string USER_ID        = '55555555-5555-5555-5555-555555555555';
    private const string OTHER_USER_ID  = '66666666-6666-6666-6666-666666666666';
    private const string ENTRY_ID       = '77777777-7777-7777-7777-777777777777';

    public function testAppointmentScheduled(): void
    {
        $event = new AppointmentScheduled(
            self::APPOINTMENT_ID,
            self::CLINIC_ID,
            self::OWNER_ID,
            self::ANIMAL_ID,
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
            'ownerId'            => self::OWNER_ID,
            'animalId'           => self::ANIMAL_ID,
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
            null,
            null,
            '2026-04-10T09:00:00+00:00',
            15,
            null,
            null,
        );

        $payload = $event->payload();
        self::assertNull($payload['ownerId']);
        self::assertNull($payload['animalId']);
        self::assertNull($payload['practitionerUserId']);
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

    public function testAppointmentPractitionerAssigneeUnassigned(): void
    {
        $event = new AppointmentPractitionerAssigneeUnassigned(
            self::APPOINTMENT_ID,
            self::CLINIC_ID,
            self::USER_ID,
        );

        self::assertSame(self::APPOINTMENT_ID, $event->aggregateId());
        self::assertSame([
            'appointmentId'              => self::APPOINTMENT_ID,
            'clinicId'                   => self::CLINIC_ID,
            'previousPractitionerUserId' => self::USER_ID,
        ], $event->payload());
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

    public function testWaitingRoomEntryCreatedFromAppointment(): void
    {
        $event = new WaitingRoomEntryCreatedFromAppointment(
            self::ENTRY_ID,
            self::CLINIC_ID,
            self::APPOINTMENT_ID,
            self::OWNER_ID,
            self::ANIMAL_ID,
            'STANDARD',
            0,
            '2026-04-10T08:55:00+00:00',
        );

        self::assertSame(self::ENTRY_ID, $event->aggregateId());
        self::assertSame([
            'waitingRoomEntryId'  => self::ENTRY_ID,
            'clinicId'            => self::CLINIC_ID,
            'linkedAppointmentId' => self::APPOINTMENT_ID,
            'ownerId'             => self::OWNER_ID,
            'animalId'            => self::ANIMAL_ID,
            'arrivalMode'         => 'STANDARD',
            'priority'            => 0,
            'arrivedAtUtc'        => '2026-04-10T08:55:00+00:00',
        ], $event->payload());
    }

    public function testWaitingRoomWalkInEntryCreated(): void
    {
        $event = new WaitingRoomWalkInEntryCreated(
            self::ENTRY_ID,
            self::CLINIC_ID,
            null,
            null,
            'Tabby cat, no collar',
            'EMERGENCY',
            5,
            'Bleeding paw',
            '2026-04-10T11:30:00+00:00',
        );

        self::assertSame(self::ENTRY_ID, $event->aggregateId());
        self::assertSame([
            'waitingRoomEntryId'     => self::ENTRY_ID,
            'clinicId'               => self::CLINIC_ID,
            'ownerId'                => null,
            'animalId'               => null,
            'foundAnimalDescription' => 'Tabby cat, no collar',
            'arrivalMode'            => 'EMERGENCY',
            'priority'               => 5,
            'triageNotes'            => 'Bleeding paw',
            'arrivedAtUtc'           => '2026-04-10T11:30:00+00:00',
        ], $event->payload());
    }

    public function testWaitingRoomEntryCalled(): void
    {
        $event = new WaitingRoomEntryCalled(
            self::ENTRY_ID,
            self::CLINIC_ID,
            '2026-04-10T09:00:00+00:00',
            self::USER_ID,
        );

        self::assertSame(self::ENTRY_ID, $event->aggregateId());
        self::assertSame([
            'waitingRoomEntryId' => self::ENTRY_ID,
            'clinicId'           => self::CLINIC_ID,
            'calledAtUtc'        => '2026-04-10T09:00:00+00:00',
            'calledByUserId'     => self::USER_ID,
        ], $event->payload());
    }

    public function testWaitingRoomEntryServiceStarted(): void
    {
        $event = new WaitingRoomEntryServiceStarted(
            self::ENTRY_ID,
            self::CLINIC_ID,
            '2026-04-10T09:05:00+00:00',
            self::USER_ID,
        );

        self::assertSame(self::ENTRY_ID, $event->aggregateId());
        self::assertSame([
            'waitingRoomEntryId'     => self::ENTRY_ID,
            'clinicId'               => self::CLINIC_ID,
            'serviceStartedAtUtc'    => '2026-04-10T09:05:00+00:00',
            'serviceStartedByUserId' => self::USER_ID,
        ], $event->payload());
    }

    public function testWaitingRoomEntryClosed(): void
    {
        $event = new WaitingRoomEntryClosed(
            self::ENTRY_ID,
            self::CLINIC_ID,
            '2026-04-10T09:30:00+00:00',
            self::USER_ID,
        );

        self::assertSame(self::ENTRY_ID, $event->aggregateId());
        self::assertSame([
            'waitingRoomEntryId' => self::ENTRY_ID,
            'clinicId'           => self::CLINIC_ID,
            'closedAtUtc'        => '2026-04-10T09:30:00+00:00',
            'closedByUserId'     => self::USER_ID,
        ], $event->payload());
    }

    public function testWaitingRoomEntryClosedAcceptsNullCloser(): void
    {
        $event = new WaitingRoomEntryClosed(
            self::ENTRY_ID,
            self::CLINIC_ID,
            '2026-04-10T09:30:00+00:00',
            null,
        );

        self::assertNull($event->payload()['closedByUserId']);
    }

    public function testWaitingRoomEntryLinkedToOwnerAndAnimal(): void
    {
        $event = new WaitingRoomEntryLinkedToOwnerAndAnimal(
            self::ENTRY_ID,
            self::CLINIC_ID,
            self::OWNER_ID,
            self::ANIMAL_ID,
        );

        self::assertSame(self::ENTRY_ID, $event->aggregateId());
        self::assertSame([
            'waitingRoomEntryId' => self::ENTRY_ID,
            'clinicId'           => self::CLINIC_ID,
            'ownerId'            => self::OWNER_ID,
            'animalId'           => self::ANIMAL_ID,
        ], $event->payload());
    }

    public function testWaitingRoomEntryTriageUpdated(): void
    {
        $event = new WaitingRoomEntryTriageUpdated(
            self::ENTRY_ID,
            self::CLINIC_ID,
            3,
            'Stable but in pain',
            'STANDARD',
        );

        self::assertSame(self::ENTRY_ID, $event->aggregateId());
        self::assertSame([
            'waitingRoomEntryId' => self::ENTRY_ID,
            'clinicId'           => self::CLINIC_ID,
            'priority'           => 3,
            'triageNotes'        => 'Stable but in pain',
            'arrivalMode'        => 'STANDARD',
        ], $event->payload());
    }
}
