<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\ValueObject;

use App\Context\Clinic\Domain\Exception\InvalidOpeningHoursException;

/**
 * A day the clinic is shut whatever the week says: a bank holiday, the annual
 * break, the afternoon of a move.
 */
final readonly class ExceptionalClosure
{
    private function __construct(
        public string $date,
        public ?string $reason,
    ) {
    }

    public static function create(string $date, ?string $reason = null): self
    {
        $date   = mb_trim($date);
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if (false === $parsed || $parsed->format('Y-m-d') !== $date) {
            throw InvalidOpeningHoursException::malformedClosureDate($date);
        }

        $reason = null !== $reason ? mb_trim($reason) : null;

        return new self($date, '' !== $reason ? $reason : null);
    }

    public function equals(self $other): bool
    {
        return $this->date === $other->date && $this->reason === $other->reason;
    }
}
