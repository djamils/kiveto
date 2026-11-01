<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\ScanExpiredPendingAppointments;

use App\Context\Scheduling\Application\Port\NotificationJobsInterface;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Only selects the expired pending requests and queues one
 * DeclineExpiredPendingAppointment each on `async`, so that one failure
 * never holds back the others; each decline re-checks its appointment under
 * its own locks (pattern of RequestDueReminders).
 */
#[AsMessageHandler]
final readonly class ScanExpiredPendingAppointmentsHandler
{
    public function __construct(
        private AppointmentRepositoryInterface $appointments,
        private NotificationJobsInterface $jobs,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ScanExpiredPendingAppointments $command): int
    {
        $expired = $this->appointments->expiredPendingIds($this->clock->now());

        foreach ($expired as $appointmentId) {
            $this->jobs->declineExpiredPending($appointmentId);
        }

        return \count($expired);
    }
}
