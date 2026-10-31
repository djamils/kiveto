<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** A weight entered on the animal file, dated by its measurement day. */
final readonly class RecordedWeight
{
    /**
     * @param \DateTimeImmutable $measuredOn a date, no time of day
     */
    public function __construct(
        public int $weightGrams,
        public \DateTimeImmutable $measuredOn,
    ) {
    }
}
