<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

/**
 * Booked when an appointment for the item's kind falls on a day of
 * `[today, max(dueOn, today) + 30 d]`; otherwise overdue before the
 * clinic-local today, due soon within 30 days, up to date beyond
 * (booked > overdue > due soon > up to date). Dates are compared as
 * calendar days, whatever their zone.
 */
final readonly class CareItemStatusPolicy
{
    public const int DUE_SOON_DAYS = 30;

    public const int BOOKED_AFTER_DUE_DAYS = 30;

    /**
     * @param list<\DateTimeImmutable> $bookedOn clinic-local days of the appointments booked for the item's kind
     */
    public function statusOf(\DateTimeImmutable $dueOn, \DateTimeImmutable $todayLocal, array $bookedOn = []): CareItemStatus
    {
        $due   = $dueOn->format('Y-m-d');
        $today = $todayLocal->format('Y-m-d');
        $until = new \DateTimeImmutable(max($due, $today))->modify('+' . self::BOOKED_AFTER_DUE_DAYS . ' days')->format('Y-m-d');

        foreach ($bookedOn as $day) {
            if ($day->format('Y-m-d') >= $today && $day->format('Y-m-d') <= $until) {
                return CareItemStatus::BOOKED;
            }
        }

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
