<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Service;

use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettings;
use App\Context\Scheduling\Domain\Exception\CancellationWindowClosedException;

/**
 * When a client may still cancel or move an appointment online (S4f, D12):
 * while `now ≤ cancellableUntil` and before its start.
 */
final class OnlineCancellationWindow
{
    /**
     * @throws CancellationWindowClosedException
     */
    public static function assertOpen(Appointment $appointment, OnlineBookingSettings $settings, \DateTimeImmutable $now): void
    {
        $startsAt = $appointment->timeSlot()->startsAtUtc();
        $until    = $settings->cancellableUntil($startsAt, $appointment->createdAt());

        if ($now >= $startsAt || $now > $until) {
            throw new CancellationWindowClosedException($appointment->id()->toString(), $until);
        }
    }
}
