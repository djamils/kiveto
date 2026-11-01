<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetOnlineAvailability;

final readonly class OnlineAvailabilityDay
{
    /**
     * @param list<\DateTimeImmutable> $slots starts in the clinic's time zone (with its offset), ascending
     */
    public function __construct(
        /** Clinic-local `YYYY-MM-DD`. */
        public string $date,
        /** An opening day, not an exceptional closure, within the horizon. */
        public bool $open,
        public array $slots,
    ) {
    }
}
