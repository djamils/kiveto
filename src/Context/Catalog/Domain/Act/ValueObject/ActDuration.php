<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Act\ValueObject;

final class ActDuration
{
    private function __construct(private readonly int $minutes)
    {
    }

    public static function ofMinutes(int $minutes): self
    {
        if ($minutes <= 0) {
            throw new \DomainException(\sprintf(
                'Act duration must be greater than 0 minutes, got %d.',
                $minutes,
            ));
        }

        return new self($minutes);
    }

    public function toMinutes(): int
    {
        return $this->minutes;
    }

    public function equals(self $other): bool
    {
        return $this->minutes === $other->minutes;
    }
}
