<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Dashboard;

/**
 * One row of the « Fil du jour »: a time and the appointments starting then,
 * or a free gap between two of them (no appointment). Times are minutes since
 * the clinic's midnight; they place the now-line.
 */
final readonly class TimelineSlot
{
    /**
     * @param list<TimelineAppointment> $appointments empty for a free gap
     */
    public function __construct(
        public string $time,
        public int $minutes,
        public int $endMinutes,
        public array $appointments,
    ) {
    }

    public function isFree(): bool
    {
        return [] === $this->appointments;
    }
}
