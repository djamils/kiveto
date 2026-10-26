<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Notification;

/**
 * When the SMS reminder of an appointment is due (Paramètres › Agenda), one
 * setting per clinic. « 24 h / 48 h avant » means the same local wall-clock
 * time one / two days before the start, in the clinic's time zone — so a
 * reminder never drifts by an hour across a DST change; « Le matin même »
 * is 08:00 local on the appointment day.
 */
enum ReminderDelay: string
{
    case H24        = 'H24';
    case H48        = 'H48';
    case SAME_DAY_8 = 'SAME_DAY_8';

    /** A reminder later than this before the start is pointless: none is planned. */
    private const string LATEST_BEFORE_START = '-30 minutes';

    public static function default(): self
    {
        return self::H24;
    }

    /**
     * The due time (UTC) this delay gives an appointment, whenever it is
     * computed; null when it would fall less than 30 minutes before the
     * start (an 08:15 or 07:30 appointment with « Le matin même »).
     */
    public function plannedFor(\DateTimeImmutable $startsAtUtc, \DateTimeZone $clinicTimeZone): ?\DateTimeImmutable
    {
        $start = $startsAtUtc->setTimezone($clinicTimeZone);

        $due = match ($this) {
            self::H24        => $start->modify('-1 day'),
            self::H48        => $start->modify('-2 days'),
            self::SAME_DAY_8 => $start->setTime(8, 0),
        };

        $due = $due->setTimezone(new \DateTimeZone('UTC'));

        return $due > $startsAtUtc->modify(self::LATEST_BEFORE_START) ? null : $due;
    }

    /**
     * The due time (UTC) of a reminder planned now; null as well when it is
     * already past — the appointment was booked too late for the delay, the
     * confirmation suffices.
     */
    public function dueAt(\DateTimeImmutable $startsAtUtc, \DateTimeZone $clinicTimeZone, \DateTimeImmutable $now): ?\DateTimeImmutable
    {
        $due = $this->plannedFor($startsAtUtc, $clinicTimeZone);

        return null === $due || $due < $now ? null : $due;
    }
}
