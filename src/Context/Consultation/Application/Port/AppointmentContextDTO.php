<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

final readonly class AppointmentContextDTO
{
    public function __construct(
        public string $clinicId,
        public ?string $admissionId,
        public string $status, // PLANNED, etc.
    ) {
    }
}
