<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Context\Scheduling\Domain\Notification\AppointmentNotificationState;
use App\Context\Scheduling\Domain\Notification\AppointmentNotificationStateRepositoryInterface;
use App\Context\Scheduling\Domain\Notification\PendingReminder;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;

/**
 * Notification states kept in memory (cloned in and out, like rows); the
 * appointments they belong to come from the given repository.
 */
final class InMemoryNotificationStates implements AppointmentNotificationStateRepositoryInterface
{
    /** @var array<string, AppointmentNotificationState> */
    public array $states = [];

    public function __construct(private readonly AppointmentRepositoryInterface $appointments)
    {
    }

    public function lock(AppointmentId $appointmentId): ?AppointmentNotificationState
    {
        $appointment = $this->appointments->findById($appointmentId);

        if (null === $appointment) {
            return null;
        }

        $this->states[$appointmentId->toString()] ??= AppointmentNotificationState::start($appointmentId, $appointment->clinicId());

        return $this->findForUpdate($appointmentId);
    }

    public function findForUpdate(AppointmentId $appointmentId): ?AppointmentNotificationState
    {
        $state = $this->states[$appointmentId->toString()] ?? null;

        return null !== $state ? clone $state : null;
    }

    public function save(AppointmentNotificationState $state): void
    {
        $this->states[$state->appointmentId()->toString()] = clone $state;
    }

    public function silence(AppointmentId $appointmentId, ClinicId $clinicId): void
    {
        $state = $this->states[$appointmentId->toString()] ?? AppointmentNotificationState::start($appointmentId, $clinicId);
        $state->silence();
        $this->states[$appointmentId->toString()] = $state;
    }

    public function dueReminders(\DateTimeImmutable $now): array
    {
        $due = array_filter($this->states, static fn (AppointmentNotificationState $state): bool => $state->isReminderDue($now));

        return array_values(array_map(static fn (AppointmentNotificationState $state): string => $state->appointmentId()->toString(), $due));
    }

    public function lockPendingReminders(ClinicId $clinicId, \DateTimeImmutable $now): array
    {
        $pending = [];

        foreach ($this->states as $state) {
            $appointment = $this->appointments->findById($state->appointmentId());

            if (null === $appointment) {
                continue;
            }

            $pendingHere = $state->clinicId()->equals($clinicId) && null === $state->reminderRequestedAt()
                && AppointmentStatus::PLANNED === $appointment->status() && $appointment->timeSlot()->startsAtUtc() > $now;

            if ($pendingHere) {
                $pending[] = new PendingReminder(clone $state, $appointment->timeSlot()->startsAtUtc());
            }
        }

        return $pending;
    }

    public function of(string $appointmentId): AppointmentNotificationState
    {
        return $this->states[$appointmentId] ?? throw new \LogicException('No state for ' . $appointmentId);
    }
}
