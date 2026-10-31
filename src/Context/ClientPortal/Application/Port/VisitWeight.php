<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** A weight read in a closed consultation's vitals, dated by the consultation start. */
final readonly class VisitWeight
{
    public function __construct(
        public int $weightGrams,
        public \DateTimeImmutable $measuredAtUtc,
    ) {
    }
}
