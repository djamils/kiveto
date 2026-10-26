<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

/**
 * Hands an appointment message to Notification, which renders, journals and
 * sends it; a replayed request (same idempotency key) never sends twice.
 */
interface NotificationRequesterInterface
{
    public function request(AppointmentMessage $message): void;
}
