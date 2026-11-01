<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Booking;

/**
 * A stretch the practitioner cannot take: an appointment that occupies its
 * slot (it also counts towards the capacity of its block), or an occurrence
 * of a non-accepting block (LEAVE, TRAINING, ADMIN, BREAK), whatever its
 * anchor date (S4b-F1). Instants, end excluded.
 */
final readonly class BusyInterval
{
    private function __construct(
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
        public bool $countsTowardsCapacity,
    ) {
        if ($start >= $end) {
            throw new \InvalidArgumentException('A busy interval must end after it starts.');
        }
    }

    public static function appointment(\DateTimeImmutable $start, \DateTimeImmutable $end): self
    {
        return new self($start, $end, true);
    }

    public static function unavailable(\DateTimeImmutable $start, \DateTimeImmutable $end): self
    {
        return new self($start, $end, false);
    }

    public function overlaps(\DateTimeImmutable $start, \DateTimeImmutable $end): bool
    {
        return $this->start < $end && $this->end > $start;
    }
}
