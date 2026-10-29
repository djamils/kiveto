<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\RequestDueReminders;

use App\Context\Scheduling\Application\Port\NotificationJobsInterface;
use App\Context\Scheduling\Domain\Notification\AppointmentNotificationStateRepositoryInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Only selects the due appointments and queues one RequestAppointmentReminder
 * each, so that one failure never holds back the others; each request
 * re-checks its appointment under its own lock.
 */
#[AsMessageHandler]
final readonly class RequestDueRemindersHandler
{
    public function __construct(
        private AppointmentNotificationStateRepositoryInterface $states,
        private NotificationJobsInterface $jobs,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RequestDueReminders $command): int
    {
        $due = $this->states->dueReminders($this->clock->now());

        foreach ($due as $appointmentId) {
            $this->jobs->requestReminder($appointmentId);
        }

        return \count($due);
    }
}
