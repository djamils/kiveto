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

    /** @var list<array{string, ?string}> appointment id, actor */
    public array $newAppointmentNotices = [];

    /** @var list<string> */
    public array $withdrawals = [];

    /** @var list<string> */
    public array $pendingBookingNotices = [];

    /** @var list<string> */
    public array $pendingBookingMoves = [];

    /** @var list<string> */
    public array $pendingBookingWithdrawals = [];

    /** @var list<string> */
    public array $expiredDeclines = [];

    public function syncLater(string $appointmentId): void
    {
        $this->syncs[] = $appointmentId;
    }

    public function requestReminder(string $appointmentId): void
    {
        $this->reminders[] = $appointmentId;
    }

    public function postNewAppointmentNotice(string $appointmentId, ?string $actorUserId): void
    {
        $this->newAppointmentNotices[] = [$appointmentId, $actorUserId];
    }

    public function withdrawNewAppointmentNotice(string $appointmentId): void
    {
        $this->withdrawals[] = $appointmentId;
    }

    public function postPendingBookingNotice(string $appointmentId): void
    {
        $this->pendingBookingNotices[] = $appointmentId;
    }

    public function movePendingBookingNotice(string $appointmentId): void
    {
        $this->pendingBookingMoves[] = $appointmentId;
    }

    public function withdrawPendingBookingNotice(string $appointmentId): void
    {
        $this->pendingBookingWithdrawals[] = $appointmentId;
    }

    public function declineExpiredPending(string $appointmentId): void
    {
        $this->expiredDeclines[] = $appointmentId;
    }
}
