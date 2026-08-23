<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\ValueObject;

use App\Context\Clinic\Domain\Exception\InvalidOpeningHoursException;

/**
 * One stretch of the day the clinic is open, in its own local time.
 */
final readonly class OpeningSlot
{
    private const string TIME = '/^([01][0-9]|2[0-3]):[0-5][0-9]$/';

    private function __construct(
        public string $opensAt,
        public string $closesAt,
    ) {
    }

    public static function create(string $opensAt, string $closesAt): self
    {
        $opensAt  = mb_trim($opensAt);
        $closesAt = mb_trim($closesAt);

        if (1 !== preg_match(self::TIME, $opensAt) || 1 !== preg_match(self::TIME, $closesAt)) {
            throw InvalidOpeningHoursException::malformedTime($opensAt, $closesAt);
        }

        // A clinic that closes before it opens is a typo, not a night shift.
        if (strcmp($closesAt, $opensAt) <= 0) {
            throw InvalidOpeningHoursException::closesBeforeItOpens($opensAt, $closesAt);
        }

        return new self($opensAt, $closesAt);
    }

    public function equals(self $other): bool
    {
        return $this->opensAt === $other->opensAt && $this->closesAt === $other->closesAt;
    }
}
