<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\ChangeReminderDelay;

use App\Context\Scheduling\Application\Port\ClinicDirectoryInterface;
use App\Context\Scheduling\Domain\Notification\AppointmentNotificationStateRepositoryInterface;
use App\Context\Scheduling\Domain\Notification\ReminderDelay;
use App\Context\Scheduling\Domain\Notification\ReminderSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Saves the clinic's reminder delay and replans the reminders not requested
 * yet of its appointments to come; reminders already requested stay as they
 * were (one reminder per appointment, ever).
 */
#[AsMessageHandler]
final readonly class ChangeReminderDelayHandler
{
    public function __construct(
        private ReminderSettingsRepositoryInterface $settings,
        private AppointmentNotificationStateRepositoryInterface $states,
        private ClinicDirectoryInterface $clinics,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ChangeReminderDelay $command): void
    {
        $delay = ReminderDelay::tryFrom($command->reminderDelay)
            ?? throw new \InvalidArgumentException(\sprintf('Unknown reminder delay "%s".', $command->reminderDelay));

        $clinicId = ClinicId::fromString($command->clinicId);

        if ($delay === $this->settings->reminderDelay($clinicId)) {
            return;
        }

        $this->settings->saveReminderDelay($clinicId, $delay);

        $now      = $this->clock->now();
        $timeZone = $this->clinics->clinic($command->clinicId)->timeZone;

        foreach ($this->states->lockPendingReminders($clinicId, $now) as $pending) {
            $pending->state->planReminder($delay, $pending->startsAtUtc, $timeZone, $now);
            $this->states->save($pending->state);
        }
    }
}
