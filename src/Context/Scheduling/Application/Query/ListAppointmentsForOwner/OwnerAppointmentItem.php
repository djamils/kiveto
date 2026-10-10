<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListAppointmentsForOwner;

final readonly class OwnerAppointmentItem
{
    public function __construct(
        public string $id,
        public string $practitionerUserId,
        public string $startsAtUtc,
        public int $durationMinutes,
        public string $status,
        public ?string $reason,
        public ?string $animalId,
        public ?string $animalLabel,
    ) {
    }
}
