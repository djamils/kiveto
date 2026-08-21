<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

final readonly class AdmissionContextDto
{
    public function __construct(
        public string $patientId,
        public string $clinicId,
        /**
         * Whether the visit is still running. One admission can carry several
         * consultations, so closing the second one must not try to close a
         * visit that the first one already ended.
         */
        public bool $isOpen = true,
    ) {
    }
}
