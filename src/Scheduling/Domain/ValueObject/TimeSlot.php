<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\ValueObject;

final readonly class TimeSlot
{
    public function __construct(
        private \DateTimeImmutable $startsAtUtc,
        private int $durationMinutes,
    ) {
        if ($durationMinutes <= 0) {
            throw new \DomainException('Duration must be greater than zero.');
        }
    }

    public function startsAtUtc(): \DateTimeImmutable
    {
        return $this->startsAtUtc;
    }

    public function durationMinutes(): int
    {
        return $this->durationMinutes;
    }

    public function endsAtUtc(): \DateTimeImmutable
    {
        return $this->startsAtUtc->modify(\sprintf('+%d minutes', $this->durationMinutes));
    }

    public function overlapsWith(self $other): bool
    {
        $thisStart  = $this->startsAtUtc;
        $thisEnd    = $this->endsAtUtc();
        $otherStart = $other->startsAtUtc();
        $otherEnd   = $other->endsAtUtc();

        // No overlap if one ends before the other starts
        return !($thisEnd <= $otherStart || $otherEnd <= $thisStart);
    }

    public function equals(self $other): bool
    {
        return $this->startsAtUtc == $other->startsAtUtc
            && $this->durationMinutes === $other->durationMinutes;
    }
}
