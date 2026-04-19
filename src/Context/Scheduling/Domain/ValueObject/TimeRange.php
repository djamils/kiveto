<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

use App\Context\Scheduling\Domain\Exception\InvalidPlanningBlockTimeRange;

final readonly class TimeRange
{
    public function __construct(
        private string $date,
        private string $startTime,
        private string $endTime,
    ) {
        $this->validate();
    }

    public function date(): string
    {
        return $this->date;
    }

    public function startTime(): string
    {
        return $this->startTime;
    }

    public function endTime(): string
    {
        return $this->endTime;
    }

    public function durationMinutes(): int
    {
        [$sh, $sm] = array_map('intval', explode(':', $this->startTime));
        [$eh, $em] = array_map('intval', explode(':', $this->endTime));

        return ($eh * 60 + $em) - ($sh * 60 + $sm);
    }

    public function toUtcStart(\DateTimeZone $tz): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($this->date . ' ' . $this->startTime, $tz))
            ->setTimezone(new \DateTimeZone('UTC'))
        ;
    }

    public function toUtcEnd(\DateTimeZone $tz): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($this->date . ' ' . $this->endTime, $tz))
            ->setTimezone(new \DateTimeZone('UTC'))
        ;
    }

    public function equals(self $other): bool
    {
        return $this->date === $other->date
            && $this->startTime === $other->startTime
            && $this->endTime === $other->endTime;
    }

    private function validate(): void
    {
        if (1 !== preg_match('/^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01])$/', $this->date)) {
            throw new InvalidPlanningBlockTimeRange('Invalid date format: ' . $this->date);
        }

        $timePattern = '/^([01]\d|2[0-3]):[0-5]\d$/';
        if (1 !== preg_match($timePattern, $this->startTime) || 1 !== preg_match($timePattern, $this->endTime)) {
            throw new InvalidPlanningBlockTimeRange(
                'Invalid time format (expected HH:MM with H∈[00–23], M∈[00–59]).'
            );
        }

        if ($this->startTime >= $this->endTime) {
            throw new InvalidPlanningBlockTimeRange('start_time must be strictly before end_time.');
        }

        if ($this->durationMinutes() < 15) {
            throw new InvalidPlanningBlockTimeRange('Minimum block duration is 15 minutes.');
        }
    }
}
