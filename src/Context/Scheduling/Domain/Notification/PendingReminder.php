<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Notification;

/**
 * A reminder not requested yet, with the current start of its appointment
 * (to recompute its due time when the clinic changes the delay).
 */
final readonly class PendingReminder
{
    public function __construct(
        public AppointmentNotificationState $state,
        public \DateTimeImmutable $startsAtUtc,
    ) {
    }
}
