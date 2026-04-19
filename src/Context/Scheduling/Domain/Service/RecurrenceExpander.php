<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Service;

use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;

final class RecurrenceExpander
{
    /**
     * Returns dates (YYYY-MM-DD) in [$rangeStart, $rangeEnd] matching the rule anchored on $baseDate.
     *
     * @return list<string>
     */
    public function expand(
        RecurrenceRule $rule,
        string $baseDate,
        \DateTimeImmutable $rangeStart,
        \DateTimeImmutable $rangeEnd,
    ): array {
        $rangeStartStr = $rangeStart->format('Y-m-d');
        $rangeEndStr   = $rangeEnd->format('Y-m-d');

        if ($rangeStartStr > $rangeEndStr) {
            return [];
        }

        if (!$rule->isRecurring()) {
            if ($baseDate >= $rangeStartStr && $baseDate <= $rangeEndStr) {
                return [$baseDate];
            }

            return [];
        }

        $until          = $rule->until() ?? $rangeEndStr;
        $effectiveEnd   = $until < $rangeEndStr ? $until : $rangeEndStr;
        $effectiveStart = $baseDate > $rangeStartStr ? $baseDate : $rangeStartStr;

        if ($effectiveStart > $effectiveEnd) {
            return [];
        }

        $baseDow = (int) (new \DateTimeImmutable($baseDate))->format('N');

        $dates  = [];
        $cursor = new \DateTimeImmutable($effectiveStart);
        $end    = new \DateTimeImmutable($effectiveEnd);

        while ($cursor->format('Y-m-d') <= $end->format('Y-m-d')) {
            $dow     = (int) $cursor->format('N');
            $matches = match ($rule->freq()) {
                RecurrenceRule::DAILY    => true,
                RecurrenceRule::WEEKLY   => $dow === $baseDow,
                RecurrenceRule::WEEKDAYS => $dow <= 5,
                default                  => false,
            };

            if ($matches) {
                $dates[] = $cursor->format('Y-m-d');
            }

            $cursor = $cursor->modify('+1 day');
        }

        return $dates;
    }
}
