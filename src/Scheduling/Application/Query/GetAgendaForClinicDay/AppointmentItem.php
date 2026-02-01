<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Query\GetAgendaForClinicDay;

final readonly class AppointmentItem
{
    public function __construct(
        public string $id,
        public string $clinicId,
        public ?string $ownerId,
        public ?string $animalId,
        public ?string $practitionerUserId,
        public string $startsAtUtc,
        public int $durationMinutes,
        public string $status,
        public ?string $reason,
        public ?string $notes,
    ) {
    }
}
