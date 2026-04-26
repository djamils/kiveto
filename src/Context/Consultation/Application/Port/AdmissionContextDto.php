<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

final readonly class AdmissionContextDto
{
    public function __construct(
        public string $patientId,
        public string $clinicId,
    ) {
    }
}
