<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Notification;

use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;

/**
 * What the client of an appointment was told, and its reminder (D2). Read
 * and written under a row lock, so that the message an appointment calls for
 * is derived once: the appointment as it is now against the last state
 * actually notified — a booking moved within the debounce window gets one
 * confirmation with its final slot, an agenda undo gets nothing, duplicate
 * or concurrent syncs find nothing left to do.
 */
final class AppointmentNotificationState
{
    private function __construct(
        private readonly AppointmentId $appointmentId,
        private readonly ClinicId $clinicId,
        private ?\DateTimeImmutable $confirmedAt,
        private ?\DateTimeImmutable $cancellationNotifiedAt,
        private bool $silenced,
        private ?NotifiedSlot $notified,
        private int $notificationCounter,
        private ?\DateTimeImmutable $reminderDueAt,
        private ?\DateTimeImmutable $reminderRequestedAt,
    ) {
    }

    public static function start(AppointmentId $appointmentId, ClinicId $clinicId): self
    {
        return new self($appointmentId, $clinicId, null, null, false, null, 0, null, null);
    }

    public static function reconstitute(
        AppointmentId $appointmentId,
        ClinicId $clinicId,
        ?\DateTimeImmutable $confirmedAt,
        ?\DateTimeImmutable $cancellationNotifiedAt,
        bool $silenced,
        ?NotifiedSlot $notified,
        int $notificationCounter,
        ?\DateTimeImmutable $reminderDueAt,
        ?\DateTimeImmutable $reminderRequestedAt,
    ): self {
        return new self($appointmentId, $clinicId, $confirmedAt, $cancellationNotifiedAt, $silenced, $notified, $notificationCounter, $reminderDueAt, $reminderRequestedAt);
    }

    /**
     * The single message the appointment calls for now, if any:
     * - planned, to come, never confirmed → confirmation;
     * - planned, to come, confirmed for another time or practitioner → change
     *   (a different duration or motif alone changes nothing);
     * - cancelled after a confirmation, the notified slot to come, not
     *   silenced, not announced yet → cancellation;
     * - anything else (cancelled before any confirmation, no-show, completed,
     *   past) → nothing.
     */
    public function nextMessage(AppointmentStatus $status, TimeSlot $slot, string $practitionerUserId, \DateTimeImmutable $now): ?AppointmentMessageKind
    {
        if (AppointmentStatus::PLANNED === $status) {
            if ($slot->startsAtUtc() <= $now) {
                return null;
            }

            if (null === $this->confirmedAt || null === $this->notified) {
                return AppointmentMessageKind::CONFIRMATION;
            }

            $moved = $this->notified->startsAtUtc->getTimestamp() !== $slot->startsAtUtc()->getTimestamp()
                || $this->notified->practitionerUserId !== $practitionerUserId;

            return $moved ? AppointmentMessageKind::CHANGE : null;
        }

        $announce = AppointmentStatus::CANCELLED === $status
            && null !== $this->confirmedAt
            && null !== $this->notified
            && !$this->silenced
            && null === $this->cancellationNotifiedAt
            && $this->notified->startsAtUtc > $now;

        return $announce ? AppointmentMessageKind::CANCELLATION : null;
    }

    /**
     * Records a message requested for the appointment and returns its number
     * (the counter, part of the idempotency key: three moves in the window
     * give one message, a new move the next day gives a new one).
     */
    public function recordNotified(AppointmentMessageKind $kind, TimeSlot $slot, string $practitionerUserId, \DateTimeImmutable $at): int
    {
        ++$this->notificationCounter;

        if (AppointmentMessageKind::CANCELLATION === $kind) {
            $this->cancellationNotifiedAt = $at;

            return $this->notificationCounter;
        }

        $this->confirmedAt ??= $at;
        $this->notified = new NotifiedSlot($slot->startsAtUtc(), $slot->durationMinutes(), $practitionerUserId);

        return $this->notificationCounter;
    }

    /**
     * The client is never told about this appointment's cancellation (death
     * of the animal, D3).
     */
    public function silence(): void
    {
        $this->silenced = true;
    }

    /**
     * Plans the reminder of the appointment's current start, unless it was
     * already requested (one reminder per appointment, ever). A due time
     * already planned for this very start and delay is kept even once past
     * (the 15-min scan picks it up); a new one is planned only if it is still
     * to come — booked too late, no reminder.
     */
    public function planReminder(ReminderDelay $delay, \DateTimeImmutable $startsAtUtc, \DateTimeZone $clinicTimeZone, \DateTimeImmutable $now): void
    {
        if (null !== $this->reminderRequestedAt) {
            return;
        }

        $planned = $delay->plannedFor($startsAtUtc, $clinicTimeZone);

        if (null !== $planned && $planned->getTimestamp() === $this->reminderDueAt?->getTimestamp()) {
            return;
        }

        $this->reminderDueAt = $delay->dueAt($startsAtUtc, $clinicTimeZone, $now);
    }

    /** No reminder for this appointment (cancelled, past, no SMS consent at send time). */
    public function dropReminder(): void
    {
        if (null === $this->reminderRequestedAt) {
            $this->reminderDueAt = null;
        }
    }

    public function isReminderDue(\DateTimeImmutable $now): bool
    {
        return null === $this->reminderRequestedAt && null !== $this->reminderDueAt && $this->reminderDueAt <= $now;
    }

    public function markReminderRequested(\DateTimeImmutable $at): void
    {
        $this->reminderRequestedAt = $at;
    }

    public function appointmentId(): AppointmentId
    {
        return $this->appointmentId;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function confirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function cancellationNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->cancellationNotifiedAt;
    }

    public function isSilenced(): bool
    {
        return $this->silenced;
    }

    public function notified(): ?NotifiedSlot
    {
        return $this->notified;
    }

    public function notificationCounter(): int
    {
        return $this->notificationCounter;
    }

    public function reminderDueAt(): ?\DateTimeImmutable
    {
        return $this->reminderDueAt;
    }

    public function reminderRequestedAt(): ?\DateTimeImmutable
    {
        return $this->reminderRequestedAt;
    }
}
