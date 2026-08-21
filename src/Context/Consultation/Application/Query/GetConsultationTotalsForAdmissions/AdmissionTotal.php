<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\GetConsultationTotalsForAdmissions;

final readonly class AdmissionTotal
{
    public function __construct(
        public string $admissionId,
        public int $totalHtMinorUnits,
        public int $totalTvaMinorUnits,
        public int $totalTtcMinorUnits,
        public string $currency,
    ) {
    }
}
