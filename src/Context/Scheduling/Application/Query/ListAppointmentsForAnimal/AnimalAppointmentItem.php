<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListAppointmentsForAnimal;

final readonly class AnimalAppointmentItem
{
    public function __construct(
        public string $id,
        public string $practitionerUserId,
        public string $startsAtUtc,
        public int $durationMinutes,
        public string $status,
        public ?string $reason,
        public ?string $notes = null,
    ) {
    }
}
