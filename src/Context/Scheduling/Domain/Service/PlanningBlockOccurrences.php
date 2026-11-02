<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Service;

use App\Context\Scheduling\Domain\Booking\BusyInterval;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;

/**
 * The occurrences of a planning block a mutation concerns (delete, update),
 * as clinic-local windows: a one-off block's own window; every occurrence
 * of a recurring one from today on, up to its `until` — or with no end at
 * all when it is open-ended. Tells which appointments the block holds. Pure.
 *
 * An open-ended block is locked `LockKeys::OPEN_ENDED_BLOCK_DAYS` ahead only
 * (FU-01): its appointments are read current (`FOR SHARE`) up to
 * `lockedUntilUtc()`, and by a plain read beyond, which takes no lock — a
 * locked read there would wait on, and deadlock with, writers of days the
 * lock does not cover. Known limit: a booking committed beyond the horizon
 * while the block changes may go unseen.
 */
final readonly class PlanningBlockOccurrences
{
    /**
     * Read bound of an open-ended recurring block: every future appointment.
     */
    public const string OPEN_ENDED_UNTIL_UTC = '9999-12-31 00:00:00';

    public function __construct(
        private TimeRange $timeRange,
        private RecurrenceRule $rule,
        private \DateTimeZone $clinicTz,
        private string $todayLocal,
    ) {
    }

    /**
     * Start of the stretch to read appointments from (UTC).
     */
    public function fromUtc(): \DateTimeImmutable
    {
        if (!$this->rule->isRecurring()) {
            return $this->timeRange->toUtcStart($this->clinicTz);
        }

        return $this->at(max($this->todayLocal, $this->timeRange->date()), $this->timeRange->startTime());
    }

    /**
     * End of the stretch to read appointments from (UTC).
     */
    public function toUtc(): \DateTimeImmutable
    {
        if (!$this->rule->isRecurring()) {
            return $this->timeRange->toUtcEnd($this->clinicTz);
        }

        $until = $this->rule->until();

        return null === $until
            ? new \DateTimeImmutable(self::OPEN_ENDED_UNTIL_UTC, new \DateTimeZone('UTC'))
            : $this->at($until, $this->timeRange->endTime());
    }

    /**
     * End of the stretch the practitioner-day lock covers (UTC): `toUtc()`,
     * except for an open-ended block (its last occurrence within the lock
     * horizon).
     */
    public function lockedUntilUtc(): \DateTimeImmutable
    {
        if (!$this->rule->isRecurring() || null !== $this->rule->until()) {
            return $this->toUtc();
        }

        $horizon = new \DateTimeImmutable($this->todayLocal, new \DateTimeZone('UTC'))
            ->modify(\sprintf('+%d days', LockKeys::OPEN_ENDED_BLOCK_DAYS))
            ->format('Y-m-d')
        ;

        return $this->at($horizon, $this->timeRange->endTime());
    }

    /**
     * The appointments of the plain read beyond the lock that the locked
     * read has not returned already (those starting before its end).
     *
     * @param list<BusyInterval> $appointments read from `lockedUntilUtc()` to `toUtc()`
     *
     * @return list<BusyInterval>
     */
    public function beyondLock(array $appointments): array
    {
        $lockedUntil = $this->lockedUntilUtc();

        return array_values(array_filter($appointments, static fn (BusyInterval $appointment): bool => $appointment->start >= $lockedUntil));
    }

    /**
     * The appointments that overlap one of the occurrences.
     *
     * @param list<BusyInterval> $appointments
     *
     * @return list<BusyInterval>
     */
    public function held(array $appointments): array
    {
        return array_values(array_filter(
            $appointments,
            fn (BusyInterval $appointment): bool => [] !== $this->windowsOverlapping($appointment),
        ));
    }

    /**
     * Whether the appointment lies entirely within one occurrence.
     */
    public function contains(BusyInterval $appointment): bool
    {
        foreach ($this->windowsOverlapping($appointment) as [$start, $end]) {
            if ($start <= $appointment->start && $appointment->end <= $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * The most appointments a single occurrence holds.
     *
     * @param list<BusyInterval> $appointments
     */
    public function busiestOccurrence(array $appointments): int
    {
        $perOccurrence = [];
        foreach ($appointments as $appointment) {
            foreach ($this->windowsOverlapping($appointment) as [$start]) {
                $key                 = $start->format('Y-m-d H:i');
                $perOccurrence[$key] = ($perOccurrence[$key] ?? 0) + 1;
            }
        }

        return [] === $perOccurrence ? 0 : max($perOccurrence);
    }

    /**
     * @return list<array{\DateTimeImmutable, \DateTimeImmutable}> occurrence windows [start, end) in UTC
     */
    private function windowsOverlapping(BusyInterval $appointment): array
    {
        $first = $appointment->start->setTimezone($this->clinicTz)->format('Y-m-d');
        // The end is exclusive: the last instant of the appointment is one second before it
        $last = $appointment->end->modify('-1 second')->setTimezone($this->clinicTz)->format('Y-m-d');

        $windows = [];
        // Calendar dates are walked in UTC so that no DST change skips or repeats a day
        $cursor = new \DateTimeImmutable($first, new \DateTimeZone('UTC'));
        while ($cursor->format('Y-m-d') <= $last) {
            $date   = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');

            if (!$this->occursOn($date)) {
                continue;
            }

            $start = $this->at($date, $this->timeRange->startTime());
            $end   = $this->at($date, $this->timeRange->endTime());

            if ($appointment->overlaps($start, $end)) {
                $windows[] = [$start, $end];
            }
        }

        return $windows;
    }

    private function occursOn(string $date): bool
    {
        if (!$this->rule->isRecurring()) {
            return $date === $this->timeRange->date();
        }

        if ($date < $this->todayLocal) {
            return false;
        }

        $day = new \DateTimeImmutable($date, new \DateTimeZone('UTC'));

        return [] !== new RecurrenceExpander()->expand($this->rule, $this->timeRange->date(), $day, $day);
    }

    private function at(string $date, string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date . ' ' . $time, $this->clinicTz)->setTimezone(new \DateTimeZone('UTC'));
    }
}
