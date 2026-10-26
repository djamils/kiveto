<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Context\Scheduling\Application\Port\NotificationJobsInterface;

final class CapturingJobs implements NotificationJobsInterface
{
    /** @var list<string> */
    public array $syncs = [];

    /** @var list<string> */
    public array $reminders = [];

    public function syncLater(string $appointmentId): void
    {
        $this->syncs[] = $appointmentId;
    }

    public function requestReminder(string $appointmentId): void
    {
        $this->reminders[] = $appointmentId;
    }
}
