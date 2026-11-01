<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Booking;

/**
 * Why a booking request ended without appointment: staff declined it, it
 * expired unanswered (P2), the clinic cancelled the slot from the agenda,
 * or the owner account was deleted (never notified).
 */
enum DeclineReason: string
{
    case DECLINED        = 'declined';
    case EXPIRED         = 'expired';
    case CANCELLED       = 'cancelled';
    case ACCOUNT_DELETED = 'account_deleted';

    /** Whether the owner is told by SMS (`PORTAL_BOOKING_DECLINED`). */
    public function isNotified(): bool
    {
        return self::ACCOUNT_DELETED !== $this;
    }
}
