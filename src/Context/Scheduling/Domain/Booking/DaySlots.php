<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Booking;

/**
 * The bookable starts of one clinic-local day. `open` = an opening day, not an
 * exceptional closure, within the horizon (S4c); a closed day has no slot.
 */
final readonly class DaySlots
{
    /**
     * @param list<\DateTimeImmutable> $slots in the clinic's time zone, ascending
     */
    public function __construct(
        public string $date,
        public bool $open,
        public array $slots,
    ) {
    }
}
