<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Notification;

/**
 * The client messages an appointment's state may call for (D2). The value
 * is the kind's part of the idempotency key.
 */
enum AppointmentMessageKind: string
{
    case CONFIRMATION = 'confirmation';
    case CHANGE       = 'change';
    case CANCELLATION = 'cancellation';
    // A pending request the clinic did not confirm (declined, expired, cancelled while pending)
    case DECLINED = 'declined';
}
