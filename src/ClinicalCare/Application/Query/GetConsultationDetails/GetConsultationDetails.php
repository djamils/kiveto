<?php

declare(strict_types=1);

namespace App\ClinicalCare\Application\Query\GetConsultationDetails;

final readonly class GetConsultationDetails
{
    public function __construct(
        public string $consultationId,
    ) {
    }
}
