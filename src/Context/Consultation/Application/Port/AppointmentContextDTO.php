<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

final readonly class AppointmentContextDTO
{
    public function __construct(
        public string $clinicId,
        public ?string $admissionId,
        // The Scheduling status as stored, carried but never interpreted here
        public string $status,
    ) {
    }
}
