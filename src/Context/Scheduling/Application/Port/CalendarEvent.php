<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

/**
 * An appointment published as a calendar event the owner adds to their own
 * calendar (Portal `.ics`): no organizer nor attendee, the uid and sequence
 * of the e-mailed invitation, tentative while the clinic has to confirm it.
 */
final readonly class CalendarEvent
{
    public function __construct(
        public string $uid,
        public int $sequence,
        public bool $tentative,
        public \DateTimeImmutable $startsAtUtc,
        public int $durationMinutes,
        public string $summary,
        public ?string $description,
        public ?string $location,
        public \DateTimeImmutable $stampedAt,
    ) {
    }
}
