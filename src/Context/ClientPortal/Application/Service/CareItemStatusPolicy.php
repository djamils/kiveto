<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

/**
 * Overdue before the clinic-local today, due soon within 30 days, up to
 * date beyond. Dates are compared as calendar days, whatever their zone.
 */
final readonly class CareItemStatusPolicy
{
    public const int DUE_SOON_DAYS = 30;

    public function statusOf(\DateTimeImmutable $dueOn, \DateTimeImmutable $todayLocal): CareItemStatus
    {
        $due   = $dueOn->format('Y-m-d');
        $today = $todayLocal->format('Y-m-d');

        if ($due < $today) {
            return CareItemStatus::OVERDUE;
        }

        if ($due <= $todayLocal->modify('+' . self::DUE_SOON_DAYS . ' days')->format('Y-m-d')) {
            return CareItemStatus::DUE_SOON;
        }

        return CareItemStatus::UP_TO_DATE;
    }

    /** The calendar day an instant falls on in a zone, at midnight in that zone. */
    public static function dayIn(\DateTimeImmutable $at, \DateTimeZone $timeZone): \DateTimeImmutable
    {
        return new \DateTimeImmutable($at->setTimezone($timeZone)->format('Y-m-d'), $timeZone);
    }
}
