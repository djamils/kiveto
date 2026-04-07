<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Command\ScheduleAppointment;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ScheduleAppointment implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public ?string $ownerId,
        public ?string $animalId,
        public ?string $practitionerUserId,
        public \DateTimeImmutable $startsAtUtc,
        public int $durationMinutes,
        public ?string $reason = null,
        public ?string $notes = null,
    ) {
    }
}
