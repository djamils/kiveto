<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** When a consultation started and how the owner reads it. */
final readonly class VisitSummary
{
    public function __construct(
        public string $consultationId,
        public \DateTimeImmutable $startedAtUtc,
        public string $title,
    ) {
    }
}
