<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Booking;

use App\Context\Scheduling\Domain\Service\BlockCapacity;

/**
 * The online slots of one practitioner for one motif (S4c, HARD rule). Pure:
 * the application gathers the intervals, this decides.
 *
 * A start `t` is offered when all of these hold:
 * - it is on the grid counted from the start of its block occurrence;
 * - `[t, t + duration)` fits inside one bookable interval and one (merged)
 *   opening window;
 * - it overlaps no busy interval;
 * - the block occurrence is not full (`BlockCapacity`, local minutes);
 * - `t ≥ earliestStart` (now + the lead time, the manual one when pending).
 *
 * Candidates are built from local minutes of the day; one the clocks skip
 * (spring gap) does not exist, the repeated autumn hour yields no duplicate.
 */
final class AvailabilityCalculator
{
    /**
     * @param list<BookableInterval> $bookable
     * @param list<OpeningWindow>    $opening
     * @param list<BusyInterval>     $busy
     *
     * @return list<DaySlots> one per local date of [fromDate, toDate]
     */
    public function days(
        string $fromDate,
        string $toDate,
        string $lastBookableDate,
        \DateTimeZone $tz,
        int $durationMinutes,
        int $gridStepMinutes,
        \DateTimeImmutable $earliestStart,
        array $bookable,
        array $opening,
        array $busy,
    ): array {
        if ($durationMinutes <= 0 || $gridStepMinutes <= 0) {
            throw new \InvalidArgumentException('The duration and the grid step must be positive.');
        }

        $days = [];

        for ($date = $fromDate; $date <= $toDate; $date = self::nextDate($date)) {
            $windows = self::mergedWindows($opening, $date, $tz);

            if ([] === $windows || $date > $lastBookableDate) {
                $days[] = new DaySlots($date, false, []);

                continue;
            }

            $slots = [];

            foreach ($bookable as $interval) {
                if ($interval->date !== $date) {
                    continue;
                }

                foreach (self::intervalSlots($interval, $tz, $durationMinutes, $gridStepMinutes, $earliestStart, $windows, $busy) as $slot) {
                    $slots[$slot->getTimestamp()] = $slot;
                }
            }

            ksort($slots);
            $days[] = new DaySlots($date, true, array_values($slots));
        }

        return $days;
    }

    /**
     * Whether that exact start would be offered: the check of a booking
     * (Task 26), recomputed with the same rules. The intervals must cover the
     * start's local day.
     *
     * @param list<BookableInterval> $bookable
     * @param list<OpeningWindow>    $opening
     * @param list<BusyInterval>     $busy
     */
    public function isBookable(
        \DateTimeImmutable $start,
        string $lastBookableDate,
        \DateTimeZone $tz,
        int $durationMinutes,
        int $gridStepMinutes,
        \DateTimeImmutable $earliestStart,
        array $bookable,
        array $opening,
        array $busy,
    ): bool {
        $date  = $start->setTimezone($tz)->format('Y-m-d');
        [$day] = $this->days($date, $date, $lastBookableDate, $tz, $durationMinutes, $gridStepMinutes, $earliestStart, $bookable, $opening, $busy);

        foreach ($day->slots as $slot) {
            if ($slot->getTimestamp() === $start->getTimestamp()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{\DateTimeImmutable, \DateTimeImmutable}> $windows
     * @param list<BusyInterval>                                  $busy
     *
     * @return list<\DateTimeImmutable>
     */
    private static function intervalSlots(
        BookableInterval $interval,
        \DateTimeZone $tz,
        int $durationMinutes,
        int $gridStepMinutes,
        \DateTimeImmutable $earliestStart,
        array $windows,
        array $busy,
    ): array {
        $blockStart = LocalTime::instant($interval->date, $interval->startMinute, $tz);
        $blockEnd   = LocalTime::instant($interval->date, $interval->endMinute, $tz);

        $occupying = 0;
        foreach ($busy as $busyInterval) {
            if ($busyInterval->countsTowardsCapacity && $busyInterval->overlaps($blockStart, $blockEnd)) {
                ++$occupying;
            }
        }

        $windowMinutes = max($interval->lengthMinutes(), $durationMinutes);
        if (BlockCapacity::isReached($interval->capacityPerHour, $windowMinutes, $occupying)) {
            return [];
        }

        $slots = [];

        for ($minute = $interval->startMinute; $minute < $interval->endMinute; $minute += $gridStepMinutes) {
            $start = LocalTime::existingInstant($interval->date, $minute, $tz);

            if (null === $start || $start < $earliestStart) {
                continue;
            }

            // Elapsed minutes: a slot across a DST change lasts its real duration
            $end = $start->setTimestamp($start->getTimestamp() + $durationMinutes * 60);

            if ($end > $blockEnd || !self::fitsOneWindow($start, $end, $windows) || self::isBusy($start, $end, $busy)) {
                continue;
            }

            $slots[] = $start;
        }

        return $slots;
    }

    /**
     * The opening windows of that date, adjacent or overlapping ones merged,
     * as instants.
     *
     * @param list<OpeningWindow> $opening
     *
     * @return list<array{\DateTimeImmutable, \DateTimeImmutable}>
     */
    private static function mergedWindows(array $opening, string $date, \DateTimeZone $tz): array
    {
        $ofDay = array_values(array_filter($opening, static fn (OpeningWindow $window): bool => $window->date === $date));
        usort($ofDay, static fn (OpeningWindow $a, OpeningWindow $b): int => $a->startMinute <=> $b->startMinute);

        /** @var list<array{int, int}> $merged */
        $merged = [];

        foreach ($ofDay as $window) {
            $last = array_key_last($merged);

            if (null !== $last && $window->startMinute <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $window->endMinute);

                continue;
            }

            $merged[] = [$window->startMinute, $window->endMinute];
        }

        return array_map(
            static fn (array $window): array => [LocalTime::instant($date, $window[0], $tz), LocalTime::instant($date, $window[1], $tz)],
            $merged,
        );
    }

    /**
     * @param list<array{\DateTimeImmutable, \DateTimeImmutable}> $windows
     */
    private static function fitsOneWindow(\DateTimeImmutable $start, \DateTimeImmutable $end, array $windows): bool
    {
        foreach ($windows as [$windowStart, $windowEnd]) {
            if ($start >= $windowStart && $end <= $windowEnd) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<BusyInterval> $busy
     */
    private static function isBusy(\DateTimeImmutable $start, \DateTimeImmutable $end, array $busy): bool
    {
        foreach ($busy as $busyInterval) {
            if ($busyInterval->overlaps($start, $end)) {
                return true;
            }
        }

        return false;
    }

    private static function nextDate(string $date): string
    {
        return new \DateTimeImmutable($date . ' 12:00', new \DateTimeZone('UTC'))->modify('+1 day')->format('Y-m-d');
    }
}
