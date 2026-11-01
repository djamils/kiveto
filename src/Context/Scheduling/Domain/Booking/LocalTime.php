<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Booking;

/**
 * Clinic-local wall-clock time as minutes of the day, turned into instants
 * the S4c way: `createFromFormat('!Y-m-d H:i', …, $tz)`, never from a UTC
 * difference, so a DST day keeps its local hours.
 */
final class LocalTime
{
    /** The last minute a day holds: intervals end at 23:59 at the latest. */
    public const int LAST_MINUTE = 1439;

    /**
     * Minutes since midnight of an `HH:MM` time.
     */
    public static function minuteOf(string $time): int
    {
        return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
    }

    /**
     * The instant of that local minute. A minute in the spring gap is moved
     * forward by PHP (02:30 → 03:30): right for the bounds of an interval.
     */
    public static function instant(string $date, int $minute, \DateTimeZone $tz): \DateTimeImmutable
    {
        $instant = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', self::label($date, $minute), $tz);

        if (false === $instant) {
            throw new \InvalidArgumentException(\sprintf('Invalid local date "%s".', $date));
        }

        return $instant;
    }

    /**
     * The instant of that local minute, or null when the clocks skip it (spring
     * gap): a slot starting at a time that does not exist is no slot.
     */
    public static function existingInstant(string $date, int $minute, \DateTimeZone $tz): ?\DateTimeImmutable
    {
        $instant = self::instant($date, $minute, $tz);

        return $instant->format('H:i') === substr(self::label($date, $minute), 11) ? $instant : null;
    }

    private static function label(string $date, int $minute): string
    {
        return \sprintf('%s %02d:%02d', $date, intdiv($minute, 60), $minute % 60);
    }
}
