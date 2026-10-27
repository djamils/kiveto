<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

/**
 * An appointment as a calendar event: an invitation (or its update) with a
 * stable uid and an increasing sequence, or its cancellation.
 */
final readonly class CalendarInvitation
{
    public function __construct(
        public string $uid,
        public int $sequence,
        public bool $cancelled,
        public \DateTimeImmutable $startsAtUtc,
        public int $durationMinutes,
        public string $summary,
        public string $description,
        public ?string $location,
        public string $organizerName,
        public string $organizerEmail,
        public string $attendeeName,
        public string $attendeeEmail,
        public \DateTimeImmutable $stampedAt,
    ) {
    }
}
