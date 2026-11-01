<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain\Event;

use App\Context\Scheduling\Domain\Event\AppointmentCancelled;
use App\Context\Scheduling\Domain\Event\AppointmentCompleted;
use App\Context\Scheduling\Domain\Event\AppointmentConfirmed;
use App\Context\Scheduling\Domain\Event\AppointmentDeclined;
use App\Context\Scheduling\Domain\Event\AppointmentMarkedNoShow;
use App\Context\Scheduling\Domain\Event\AppointmentOwnerChanged;
use App\Context\Scheduling\Domain\Event\AppointmentPractitionerAssigneeChanged;
use App\Context\Scheduling\Domain\Event\AppointmentPractitionerAssigneeUnassigned;
use App\Context\Scheduling\Domain\Event\AppointmentRequested;
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
            'owner-1',
            'animal-1',
            self::OTHER_USER_ID,
        );

        self::assertSame(self::APPOINTMENT_ID, $event->aggregateId());
        self::assertSame('scheduling.appointment.scheduled.v1', $event->name());
        self::assertSame(
            [self::APPOINTMENT_ID, self::CLINIC_ID, self::USER_ID, 'owner-1', 'animal-1', self::OTHER_USER_ID],
            [$event->appointmentId(), $event->clinicId(), $event->practitionerUserId(), $event->ownerId(), $event->animalId(), $event->actorUserId()],
        );
        self::assertSame([
            'appointmentId'      => self::APPOINTMENT_ID,
            'clinicId'           => self::CLINIC_ID,
            'linkedAdmissionId'  => self::ADMISSION_ID,
            'practitionerUserId' => self::USER_ID,
            'startsAtUtc'        => '2026-04-10T09:00:00+00:00',
            'durationMinutes'    => 30,
            'reason'             => 'Routine checkup',
            'notes'              => 'Anxious patient',
            'ownerId'            => 'owner-1',
            'animalId'           => 'animal-1',
            'actorUserId'        => self::OTHER_USER_ID,
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
        self::assertNull($event->ownerId());
        self::assertNull($event->actorUserId());
    }

    public function testAppointmentCancelled(): void
    {
        $event = new AppointmentCancelled(self::APPOINTMENT_ID, self::CLINIC_ID);

        self::assertSame(self::APPOINTMENT_ID, $event->aggregateId());
        self::assertSame('scheduling.appointment.cancelled.v1', $event->name());
        self::assertSame([
            'appointmentId'      => self::APPOINTMENT_ID,
            'clinicId'           => self::CLINIC_ID,
            'notifyClient'       => true,
            'cancelledBy'        => 'clinic',
            'cancellationReason' => null,
            'wasPending'         => false,
            'source'             => 'clinic',
            'ownerId'            => null,
            'animalId'           => null,
            'startsAtUtc'        => null,
        ], $event->payload());
        self::assertSame([self::APPOINTMENT_ID, self::CLINIC_ID, true], [$event->appointmentId(), $event->clinicId(), $event->notifyClient()]);
        self::assertFalse(new AppointmentCancelled(self::APPOINTMENT_ID, self::CLINIC_ID, notifyClient: false)->notifyClient());

        $byClient = new AppointmentCancelled(self::APPOINTMENT_ID, self::CLINIC_ID, true, 'client', 'seen_elsewhere', true, 'portal', 'owner-1', 'animal-1', '2026-10-22T07:30:00+00:00');
        self::assertSame(
            ['client', 'seen_elsewhere', true, 'portal', 'owner-1', 'animal-1', '2026-10-22T07:30:00+00:00'],
            [$byClient->cancelledBy(), $byClient->cancellationReason(), $byClient->wasPending(), $byClient->source(), $byClient->ownerId(), $byClient->animalId(), $byClient->startsAtUtc()],
        );
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
        self::assertSame(self::APPOINTMENT_ID, $event->appointmentId());
        self::assertSame([
            'appointmentId'         => self::APPOINTMENT_ID,
            'clinicId'              => self::CLINIC_ID,
            'oldPractitionerUserId' => self::USER_ID,
            'newPractitionerUserId' => self::OTHER_USER_ID,
        ], $event->payload());
    }

    public function testAppointmentPractitionerAssigneeUnassigned(): void
    {
        $event = new AppointmentPractitionerAssigneeUnassigned(self::APPOINTMENT_ID, self::CLINIC_ID, self::USER_ID);

        self::assertSame(self::APPOINTMENT_ID, $event->aggregateId());
        self::assertSame('scheduling.appointment-practitioner-assignee.unassigned.v1', $event->name());
        self::assertSame([
            'appointmentId'              => self::APPOINTMENT_ID,
            'clinicId'                   => self::CLINIC_ID,
            'previousPractitionerUserId' => self::USER_ID,
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

    public function testAppointmentOwnerChanged(): void
    {
        $event = new AppointmentOwnerChanged(self::APPOINTMENT_ID, self::CLINIC_ID, null, 'owner-2');

        self::assertSame(self::APPOINTMENT_ID, $event->aggregateId());
        self::assertSame(self::APPOINTMENT_ID, $event->appointmentId());
        self::assertSame([
            'appointmentId' => self::APPOINTMENT_ID,
            'clinicId'      => self::CLINIC_ID,
            'oldOwnerId'    => null,
            'newOwnerId'    => 'owner-2',
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
        self::assertSame(self::APPOINTMENT_ID, $event->appointmentId());
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

    public function testAppointmentRequested(): void
    {
        $event = new AppointmentRequested(
            appointmentId: self::APPOINTMENT_ID,
            clinicId: self::CLINIC_ID,
            linkedAdmissionId: null,
            practitionerUserId: self::USER_ID,
            startsAtUtc: '2026-04-10T09:00:00+00:00',
            durationMinutes: 20,
            reason: 'Vaccination',
            notes: null,
            ownerId: 'owner-1',
            animalId: 'animal-1',
            bookingMotifId: 'motif-1',
            source: 'portal',
        );

        self::assertSame(self::APPOINTMENT_ID, $event->aggregateId());
        self::assertSame('scheduling.appointment.requested.v1', $event->name());
        self::assertSame([
            'appointmentId'      => self::APPOINTMENT_ID,
            'clinicId'           => self::CLINIC_ID,
            'linkedAdmissionId'  => null,
            'practitionerUserId' => self::USER_ID,
            'startsAtUtc'        => '2026-04-10T09:00:00+00:00',
            'durationMinutes'    => 20,
            'reason'             => 'Vaccination',
            'notes'              => null,
            'ownerId'            => 'owner-1',
            'animalId'           => 'animal-1',
            'bookingMotifId'     => 'motif-1',
            'source'             => 'portal',
        ], $event->payload());
        self::assertSame(
            [self::APPOINTMENT_ID, self::CLINIC_ID, self::USER_ID, '2026-04-10T09:00:00+00:00', 'owner-1', 'animal-1', 'motif-1', 'portal'],
            [$event->appointmentId(), $event->clinicId(), $event->practitionerUserId(), $event->startsAtUtc(), $event->ownerId(), $event->animalId(), $event->bookingMotifId(), $event->source()],
        );
    }

    public function testAppointmentConfirmed(): void
    {
        $event = new AppointmentConfirmed(self::APPOINTMENT_ID, self::CLINIC_ID, self::USER_ID, '2026-04-10T09:00:00+00:00', 'owner-1', null, 'portal', true);

        self::assertSame(self::APPOINTMENT_ID, $event->aggregateId());
        self::assertSame('scheduling.appointment.confirmed.v1', $event->name());
        self::assertSame([
            'appointmentId'      => self::APPOINTMENT_ID,
            'clinicId'           => self::CLINIC_ID,
            'practitionerUserId' => self::USER_ID,
            'startsAtUtc'        => '2026-04-10T09:00:00+00:00',
            'ownerId'            => 'owner-1',
            'animalId'           => null,
            'source'             => 'portal',
            'silent'             => true,
        ], $event->payload());
        self::assertSame(
            [self::APPOINTMENT_ID, self::CLINIC_ID, self::USER_ID, '2026-04-10T09:00:00+00:00', 'owner-1', null, 'portal', true],
            [$event->appointmentId(), $event->clinicId(), $event->practitionerUserId(), $event->startsAtUtc(), $event->ownerId(), $event->animalId(), $event->source(), $event->silent()],
        );
    }

    public function testAppointmentDeclined(): void
    {
        $event = new AppointmentDeclined(self::APPOINTMENT_ID, self::CLINIC_ID, self::USER_ID, '2026-04-10T09:00:00+00:00', null, 'animal-1', 'portal', 'expired');

        self::assertSame(self::APPOINTMENT_ID, $event->aggregateId());
        self::assertSame('scheduling.appointment.declined.v1', $event->name());
        self::assertSame([
            'appointmentId'      => self::APPOINTMENT_ID,
            'clinicId'           => self::CLINIC_ID,
            'practitionerUserId' => self::USER_ID,
            'startsAtUtc'        => '2026-04-10T09:00:00+00:00',
            'ownerId'            => null,
            'animalId'           => 'animal-1',
            'source'             => 'portal',
            'reason'             => 'expired',
            'notifyClient'       => true,
        ], $event->payload());
        self::assertSame(
            [self::APPOINTMENT_ID, self::CLINIC_ID, self::USER_ID, '2026-04-10T09:00:00+00:00', null, 'animal-1', 'portal', 'expired', true],
            [$event->appointmentId(), $event->clinicId(), $event->practitionerUserId(), $event->startsAtUtc(), $event->ownerId(), $event->animalId(), $event->source(), $event->reason(), $event->notifyClient()],
        );
    }
}
