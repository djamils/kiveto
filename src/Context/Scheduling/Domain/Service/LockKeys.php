<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Service;

use App\Context\Scheduling\Domain\ValueObject\PractitionerDay;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;
use App\Context\Scheduling\Domain\ValueObject\UserId;

/**
 * The practitioner-day keys a write must lock (S4d). Pure: every result is
 * deduplicated and sorted by (practitioner, local date), the order in which
 * the lock takes them, so two writers never wait on each other crosswise.
 */
final class LockKeys
{
    /**
     * Open-ended recurring blocks lock this many days ahead of today.
     */
    public const int OPEN_ENDED_BLOCK_DAYS = 60;

    /**
     * Every clinic-local date the slot [start, end) touches: 23:30–00:30
     * locks both days, 23:00–00:00 only the first one.
     *
     * @return list<PractitionerDay>
     */
    public static function forSlot(
        UserId $practitionerId,
        \DateTimeImmutable $startUtc,
        \DateTimeImmutable $endUtc,
        \DateTimeZone $clinicTz,
    ): array {
        $first = $startUtc->setTimezone($clinicTz)->format('Y-m-d');
        // The end is exclusive: the last instant of the slot is one second before it
        $last = $endUtc > $startUtc
            ? $endUtc->modify('-1 second')->setTimezone($clinicTz)->format('Y-m-d')
            : $first;

        $keys = [];
        // Calendar dates are walked in UTC so that no DST change skips or repeats a day
        $cursor = new \DateTimeImmutable($first, new \DateTimeZone('UTC'));
        while ($cursor->format('Y-m-d') <= $last) {
            $keys[] = new PractitionerDay($practitionerId, $cursor->format('Y-m-d'));
            $cursor = $cursor->modify('+1 day');
        }

        return $keys;
    }

    /**
     * The dates of the block occurrences a mutation touches: a one-off block
     * locks its date; a recurring one every occurrence from today (or its
     * first date) up to its `until`, or OPEN_ENDED_BLOCK_DAYS ahead.
     *
     * @return list<PractitionerDay>
     */
    public static function forBlock(
        UserId $practitionerId,
        TimeRange $timeRange,
        RecurrenceRule $rule,
        string $todayLocal,
    ): array {
        if (!$rule->isRecurring()) {
            return [new PractitionerDay($practitionerId, $timeRange->date())];
        }

        $today = new \DateTimeImmutable($todayLocal, new \DateTimeZone('UTC'));
        $end   = null !== $rule->until()
            ? new \DateTimeImmutable($rule->until(), new \DateTimeZone('UTC'))
            : $today->modify(\sprintf('+%d days', self::OPEN_ENDED_BLOCK_DAYS));

        $dates = new RecurrenceExpander()->expand($rule, $timeRange->date(), $today, $end);

        return array_map(static fn (string $date): PractitionerDay => new PractitionerDay($practitionerId, $date), $dates);
    }

    /**
     * Union of key sets, deduplicated and sorted.
     *
     * @param list<PractitionerDay> ...$sets
     *
     * @return list<PractitionerDay>
     */
    public static function merge(array ...$sets): array
    {
        $byKey = [];
        foreach ($sets as $set) {
            foreach ($set as $day) {
                $byKey[$day->key()] = $day;
            }
        }

        ksort($byKey, \SORT_STRING);

        return array_values($byKey);
    }
}
