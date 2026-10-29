<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Inbox;

/**
 * What a bell entry is about. Every kind is shown under the « Activité »
 * filter (no « Tâches » until tasks exist).
 */
enum InAppKind: string
{
    case NEW_APPOINTMENT = 'NEW_APPOINTMENT';
    case CRITICAL_STOCK  = 'CRITICAL_STOCK';
    case EXPIRING_LOTS   = 'EXPIRING_LOTS';
    case SMS_QUOTA       = 'SMS_QUOTA';

    public const string FILTER_ACTIVITY = 'activite';

    /** The bell filter the entry is listed under. */
    public function filter(): string
    {
        return self::FILTER_ACTIVITY;
    }
}
