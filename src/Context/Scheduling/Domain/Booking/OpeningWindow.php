<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Booking;

/**
 * A stretch of a day the clinic is open, in clinic-local minutes. Adjacent
 * windows are merged by the calculator (08:00–12:30 + 12:30–18:00 is one).
 */
final readonly class OpeningWindow
{
    public function __construct(
        public string $date,
        public int $startMinute,
        public int $endMinute,
    ) {
        if ($startMinute < 0 || $endMinute > LocalTime::LAST_MINUTE || $startMinute >= $endMinute) {
            throw new \InvalidArgumentException('An opening window must end after it starts, within its day.');
        }
    }
}
