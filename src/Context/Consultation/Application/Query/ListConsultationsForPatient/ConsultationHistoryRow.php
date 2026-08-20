<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\ListConsultationsForPatient;

final readonly class ConsultationHistoryRow
{
    public function __construct(
        public string $consultationId,
        public string $startedAtUtc,
        public ?string $closedAtUtc,
        public string $status,
        public ?string $chiefComplaint,
        public ?string $summary,
        public ?string $weightKg,
    ) {
    }
}
