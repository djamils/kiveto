<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Dashboard;

/**
 * A patient currently in hospitalisation.
 */
final readonly class HospitalBed
{
    public function __construct(
        public string $admissionId,
        public string $emoji,
        public string $name,
        public string $subtitle,
        public string $stayLabel,
        public string $admittedLabel,
        public string $sinceLabel,
        public string $practitioner,
        public ?string $animalId,
    ) {
    }
}
