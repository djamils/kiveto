<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Booking;

/**
 * One occurrence of an accepting planning block of a type the motif allows,
 * in clinic-local minutes of its day. Its capacity is counted per occurrence.
 */
final readonly class BookableInterval
{
    public function __construct(
        public string $date,
        public int $startMinute,
        public int $endMinute,
        public int $capacityPerHour,
    ) {
        if ($startMinute < 0 || $endMinute > LocalTime::LAST_MINUTE || $startMinute >= $endMinute) {
            throw new \InvalidArgumentException('A bookable interval must end after it starts, within its day.');
        }
    }

    /** Length in local minutes: what `BlockCapacity` counts (S4b-F2). */
    public function lengthMinutes(): int
    {
        return $this->endMinute - $this->startMinute;
    }
}
