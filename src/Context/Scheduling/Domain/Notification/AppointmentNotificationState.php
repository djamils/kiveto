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
 *
 * The owner told is recorded too. When another client takes the booking
 * over (or none), the one who was told hears of its cancellation and the
 * state starts again for the new one, who then gets a confirmation: two
 * messages, to two clients, from one sync — still derived once, under the
 * lock, each with its own number.
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
        private ?string $notifiedOwnerId,
    ) {
    }

    public static function start(AppointmentId $appointmentId, ClinicId $clinicId): self
    {
        return new self($appointmentId, $clinicId, null, null, false, null, 0, null, null, null);
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
        ?string $notifiedOwnerId = null,
    ): self {
        return new self($appointmentId, $clinicId, $confirmedAt, $cancellationNotifiedAt, $silenced, $notified, $notificationCounter, $reminderDueAt, $reminderRequestedAt, $notifiedOwnerId);
    }

    /**
     * The client told about the appointment when another one (or none) has
     * taken it over since, while it is still planned and to come — that
     * client is to hear of its cancellation (then releaseOwner()). Null when
     * nothing was confirmed, the owner is unchanged, or unknown (states
     * recorded before owners were).
     */
    public function replacedOwner(AppointmentStatus $status, ?string $ownerId, \DateTimeImmutable $now): ?string
    {
        $replaced = AppointmentStatus::PLANNED === $status
            && null !== $this->confirmedAt
            && null !== $this->notified
            && null !== $this->notifiedOwnerId
            && $this->notifiedOwnerId !== $ownerId
            && $this->notified->startsAtUtc > $now;

        return $replaced ? $this->notifiedOwnerId : null;
    }

    /**
     * The former client was told (or could not be): nothing was confirmed
     * to the current one yet — the next message is their confirmation. The
     * counter is kept, so numbers keep growing.
     */
    public function releaseOwner(): void
    {
        $this->confirmedAt            = null;
        $this->cancellationNotifiedAt = null;
        $this->notified               = null;
        $this->notifiedOwnerId        = null;
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
     * give one message, a new move the next day gives a new one). A
     * confirmation or a change records the client told.
     */
    public function recordNotified(AppointmentMessageKind $kind, TimeSlot $slot, string $practitionerUserId, \DateTimeImmutable $at, ?string $ownerId = null): int
    {
        ++$this->notificationCounter;

        if (AppointmentMessageKind::CANCELLATION === $kind) {
            $this->cancellationNotifiedAt = $at;

            return $this->notificationCounter;
        }

        $this->confirmedAt ??= $at;
        $this->notifiedOwnerId = $ownerId;
        $this->notified        = new NotifiedSlot($slot->startsAtUtc(), $slot->durationMinutes(), $practitionerUserId);

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

    /**
     * Replans a reminder not requested yet when the delay changed or right
     * before it is sent (the appointment may have moved since its last sync):
     * the due time of the current start and delay when still to come. When
     * it is already past, a reminder the client was still waiting for (one
     * was planned) is due now — sent at the next scan — unless the
     * appointment starts within 30 minutes; with none planned, the
     * appointment was booked too late for the delay and gets none.
     */
    public function replanReminder(ReminderDelay $delay, \DateTimeImmutable $startsAtUtc, \DateTimeZone $clinicTimeZone, \DateTimeImmutable $now): void
    {
        if (null !== $this->reminderRequestedAt) {
            return;
        }

        $planned = $delay->plannedFor($startsAtUtc, $clinicTimeZone);

        if (null === $planned || $planned >= $now) {
            $this->reminderDueAt = $planned;

            return;
        }

        $stillUseful = $now <= $startsAtUtc->modify(ReminderDelay::LATEST_BEFORE_START);

        $this->reminderDueAt = null !== $this->reminderDueAt && $stillUseful ? $now : null;
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

    /** The client the last confirmation or change went to. */
    public function notifiedOwnerId(): ?string
    {
        return $this->notifiedOwnerId;
    }
}
