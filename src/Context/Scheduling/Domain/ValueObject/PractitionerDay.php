<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

/**
 * One key of the practitioner-day lock: a practitioner's agenda on one
 * clinic-local date (YYYY-MM-DD).
 */
final readonly class PractitionerDay
{
    public function __construct(
        public UserId $practitionerId,
        public string $localDate,
    ) {
        if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $localDate)) {
            throw new \InvalidArgumentException(\sprintf('Invalid local date "%s".', $localDate));
        }
    }

    /**
     * Sort and identity key: practitioner first, then date.
     */
    public function key(): string
    {
        return $this->practitionerId->toString() . '|' . $this->localDate;
    }
}
