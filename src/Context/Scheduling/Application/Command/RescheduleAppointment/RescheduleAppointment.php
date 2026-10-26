<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\RescheduleAppointment;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RescheduleAppointment implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $appointmentId,
        public \DateTimeImmutable $startsAtUtc,
        public int $durationMinutes,
        public string $practitionerUserId,
        public ?string $ownerId = null,
        public ?string $animalId = null,
        // Motif and note of the booking: rewritten when $changeDetails is true
        public bool $changeDetails = false,
        public ?string $reason = null,
        public ?string $notes = null,
        public ?string $actorUserId = null,
    ) {
    }
}
