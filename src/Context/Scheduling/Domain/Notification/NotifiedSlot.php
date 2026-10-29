<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Notification;

/**
 * The slot and the practitioner the client was last told about.
 */
final readonly class NotifiedSlot
{
    public function __construct(
        public \DateTimeImmutable $startsAtUtc,
        public int $durationMinutes,
        public string $practitionerUserId,
    ) {
    }
}
