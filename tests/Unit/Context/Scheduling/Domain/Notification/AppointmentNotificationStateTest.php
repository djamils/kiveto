<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain\Notification;

use App\Context\Scheduling\Domain\Notification\AppointmentMessageKind;
use App\Context\Scheduling\Domain\Notification\AppointmentNotificationState;
use App\Context\Scheduling\Domain\Notification\NotifiedSlot;
use App\Context\Scheduling\Domain\Notification\ReminderDelay;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Tests\Unit\Context\Scheduling\Support\Appointments;
use PHPUnit\Framework\TestCase;

final class AppointmentNotificationStateTest extends TestCase
{
    private const string NOW = '2026-10-13 08:42';

    public function testAPlannedAppointmentToComeIsConfirmedOnce(): void
    {
        $state = self::fresh();
        $slot  = Appointments::slot('2026-10-22 07:30');

        self::assertSame(AppointmentMessageKind::CONFIRMATION, $state->nextMessage(AppointmentStatus::PLANNED, $slot, Appointments::ROUSSEAU, self::now()));
        self::assertSame(1, $state->recordNotified(AppointmentMessageKind::CONFIRMATION, $slot, Appointments::ROUSSEAU, self::now()));

        self::assertNull($state->nextMessage(AppointmentStatus::PLANNED, $slot, Appointments::ROUSSEAU, self::now()));
        self::assertSame(self::NOW, $state->confirmedAt()?->format('Y-m-d H:i'));
        self::assertSame(['2026-10-22 07:30', 30, Appointments::ROUSSEAU], self::described($state->notified()));
        self::assertSame(1, $state->notificationCounter());
    }

    public function testAPastOrBookedThenCancelledAppointmentCallsForNothing(): void
    {
        $state = self::fresh();

        self::assertNull($state->nextMessage(AppointmentStatus::PLANNED, Appointments::slot('2026-10-13 08:30'), Appointments::ROUSSEAU, self::now()));
        self::assertNull($state->nextMessage(AppointmentStatus::CANCELLED, Appointments::slot('2026-10-22 07:30'), Appointments::ROUSSEAU, self::now()));
    }

    public function testATimeOrPractitionerChangeCallsForAChangeMessage(): void
    {
        $state = self::confirmed();

        self::assertSame(AppointmentMessageKind::CHANGE, $state->nextMessage(AppointmentStatus::PLANNED, Appointments::slot('2026-10-23 09:00'), Appointments::ROUSSEAU, self::now()));
        self::assertSame(AppointmentMessageKind::CHANGE, $state->nextMessage(AppointmentStatus::PLANNED, Appointments::slot('2026-10-22 07:30'), Appointments::LAMBERT, self::now()));
        // A longer slot at the same time, or a move undone, says nothing.
        self::assertNull($state->nextMessage(AppointmentStatus::PLANNED, Appointments::slot('2026-10-22 07:30', 45), Appointments::ROUSSEAU, self::now()));

        self::assertSame(2, $state->recordNotified(AppointmentMessageKind::CHANGE, Appointments::slot('2026-10-23 09:00'), Appointments::LAMBERT, self::now()->modify('+1 day')));
        self::assertSame(self::NOW, $state->confirmedAt()?->format('Y-m-d H:i'));
        self::assertSame(['2026-10-23 09:00', 30, Appointments::LAMBERT], self::described($state->notified()));
    }

    public function testACancellationAfterAConfirmationIsToldOnce(): void
    {
        $state = self::confirmed();
        $slot  = Appointments::slot('2026-10-22 07:30');

        self::assertSame(AppointmentMessageKind::CANCELLATION, $state->nextMessage(AppointmentStatus::CANCELLED, $slot, Appointments::ROUSSEAU, self::now()));
        self::assertSame(2, $state->recordNotified(AppointmentMessageKind::CANCELLATION, $slot, Appointments::ROUSSEAU, self::now()));

        self::assertSame(self::NOW, $state->cancellationNotifiedAt()?->format('Y-m-d H:i'));
        self::assertNull($state->nextMessage(AppointmentStatus::CANCELLED, $slot, Appointments::ROUSSEAU, self::now()));
    }

    public function testNoCancellationMessageWhenSilencedPastOrNotCancelled(): void
    {
        $slot = Appointments::slot('2026-10-22 07:30');

        $silenced = self::confirmed();
        $silenced->silence();
        self::assertTrue($silenced->isSilenced());
        self::assertNull($silenced->nextMessage(AppointmentStatus::CANCELLED, $slot, Appointments::ROUSSEAU, self::now()));

        // The notified slot is over: nothing to cancel any more.
        self::assertNull(self::confirmed()->nextMessage(AppointmentStatus::CANCELLED, $slot, Appointments::ROUSSEAU, Appointments::utc('2026-10-22 08:00')));
        // No-show and completed appointments are never announced.
        self::assertNull(self::confirmed()->nextMessage(AppointmentStatus::NO_SHOW, $slot, Appointments::ROUSSEAU, self::now()));
        self::assertNull(self::confirmed()->nextMessage(AppointmentStatus::COMPLETED, $slot, Appointments::ROUSSEAU, self::now()));
    }

    public function testTheReminderIsPlannedFromTheCurrentSlot(): void
    {
        $state = self::fresh();
        $paris = new \DateTimeZone('Europe/Paris');

        $state->planReminder(ReminderDelay::H24, Appointments::utc('2026-10-22 07:30'), $paris, self::now());
        self::assertSame('2026-10-21 07:30', $state->reminderDueAt()?->format('Y-m-d H:i'));

        // Moved before the reminder was requested: the reminder follows (AC 21).
        $state->planReminder(ReminderDelay::H24, Appointments::utc('2026-10-23 09:00'), $paris, self::now());
        self::assertSame('2026-10-22 09:00', $state->reminderDueAt()?->format('Y-m-d H:i'));

        // Its due time is kept once past, until the scan requests it.
        $state->planReminder(ReminderDelay::H24, Appointments::utc('2026-10-23 09:00'), $paris, Appointments::utc('2026-10-22 09:05'));
        self::assertTrue($state->isReminderDue(Appointments::utc('2026-10-22 09:05')));
        self::assertFalse($state->isReminderDue(Appointments::utc('2026-10-22 08:59')));

        // Moved to a slot too close for the delay: no reminder.
        $state->planReminder(ReminderDelay::H24, Appointments::utc('2026-10-22 10:00'), $paris, Appointments::utc('2026-10-22 09:05'));
        self::assertNull($state->reminderDueAt());
        self::assertFalse($state->isReminderDue(Appointments::utc('2026-10-22 09:05')));
    }

    public function testARequestedReminderIsNeverPlannedNorDroppedAgain(): void
    {
        $state = self::fresh();
        $paris = new \DateTimeZone('Europe/Paris');
        $state->planReminder(ReminderDelay::H24, Appointments::utc('2026-10-22 07:30'), $paris, self::now());
        $state->markReminderRequested(Appointments::utc('2026-10-21 07:30'));

        $state->planReminder(ReminderDelay::H24, Appointments::utc('2026-10-24 07:30'), $paris, self::now());
        $state->dropReminder();

        self::assertSame('2026-10-21 07:30', $state->reminderDueAt()?->format('Y-m-d H:i'));
        self::assertSame('2026-10-21 07:30', $state->reminderRequestedAt()?->format('Y-m-d H:i'));
        self::assertFalse($state->isReminderDue(Appointments::utc('2026-10-23 07:30')));
    }

    public function testADroppedReminderIsNoLongerDue(): void
    {
        $state = self::fresh();
        $state->planReminder(ReminderDelay::H48, Appointments::utc('2026-10-22 07:30'), new \DateTimeZone('Europe/Paris'), self::now());
        $state->dropReminder();

        self::assertNull($state->reminderDueAt());
    }

    public function testAStateIsReconstitutedAsStored(): void
    {
        $notified = new NotifiedSlot(Appointments::utc('2026-10-22 07:30'), 30, Appointments::ROUSSEAU);
        $state    = AppointmentNotificationState::reconstitute(
            AppointmentId::fromString(Appointments::ID),
            ClinicId::fromString(Appointments::CLINIC),
            self::now(),
            null,
            false,
            $notified,
            3,
            Appointments::utc('2026-10-21 07:30'),
            null,
        );

        self::assertSame([Appointments::ID, Appointments::CLINIC, 3, false], [$state->appointmentId()->toString(), $state->clinicId()->toString(), $state->notificationCounter(), $state->isSilenced()]);
        self::assertSame($notified, $state->notified());
        self::assertNull($state->cancellationNotifiedAt());
        self::assertNull($state->reminderRequestedAt());
    }

    private static function fresh(): AppointmentNotificationState
    {
        return AppointmentNotificationState::start(AppointmentId::fromString(Appointments::ID), ClinicId::fromString(Appointments::CLINIC));
    }

    private static function confirmed(): AppointmentNotificationState
    {
        $state = self::fresh();
        $state->recordNotified(AppointmentMessageKind::CONFIRMATION, Appointments::slot('2026-10-22 07:30'), Appointments::ROUSSEAU, self::now());

        return $state;
    }

    /**
     * @return array{0: string, 1: int, 2: string}|null
     */
    private static function described(?NotifiedSlot $slot): ?array
    {
        return null !== $slot ? [$slot->startsAtUtc->format('Y-m-d H:i'), $slot->durationMinutes, $slot->practitionerUserId] : null;
    }

    private static function now(): \DateTimeImmutable
    {
        return Appointments::utc(self::NOW);
    }
}
