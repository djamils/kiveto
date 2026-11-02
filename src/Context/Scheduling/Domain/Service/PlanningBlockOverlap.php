<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Service;

use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;

/**
 * No two blocks of a staff member overlap (FU-06), occurrence by occurrence:
 * a recurring block is compared on every date it occurs, not on its first
 * date only. A one-off block is compared on its date; a recurring one on its
 * occurrences from today (or its first date, if later) over
 * `LockKeys::OPEN_ENDED_BLOCK_DAYS` days — the online booking horizon — up
 * to its `until`. Pure.
 */
final readonly class PlanningBlockOverlap
{
    /** @var list<string> */
    private array $dates;

    public function __construct(
        private TimeRange $timeRange,
        RecurrenceRule $rule,
        string $todayLocal,
    ) {
        $this->dates = self::datesOf($timeRange, $rule, $todayLocal);
    }

    /**
     * First local date compared.
     */
    public function firstDate(): ?string
    {
        return $this->dates[0] ?? null;
    }

    /**
     * Last local date compared.
     */
    public function lastDate(): ?string
    {
        return [] === $this->dates ? null : $this->dates[\count($this->dates) - 1];
    }

    /**
     * Whether an occurrence of the other block meets one of this block's,
     * at overlapping times. Times are 'HH:MM' (seconds, if any, ignored).
     */
    public function meets(string $otherDate, string $otherStartTime, string $otherEndTime, RecurrenceRule $otherRule): bool
    {
        $start = substr($otherStartTime, 0, 5);
        $end   = substr($otherEndTime, 0, 5);

        if (!($this->timeRange->startTime() < $end && $this->timeRange->endTime() > $start)) {
            return false;
        }

        $expander = new RecurrenceExpander();
        foreach ($this->dates as $date) {
            $day = new \DateTimeImmutable($date, new \DateTimeZone('UTC'));

            if ([] !== $expander->expand($otherRule, $otherDate, $day, $day)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function datesOf(TimeRange $timeRange, RecurrenceRule $rule, string $todayLocal): array
    {
        if (!$rule->isRecurring()) {
            return [$timeRange->date()];
        }

        $from = new \DateTimeImmutable(max($todayLocal, $timeRange->date()), new \DateTimeZone('UTC'));
        $to   = $from->modify(\sprintf('+%d days', LockKeys::OPEN_ENDED_BLOCK_DAYS));

        return new RecurrenceExpander()->expand($rule, $timeRange->date(), $from, $to);
    }
}
