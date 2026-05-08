<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\GetConsultationDetails;

final readonly class GetConsultationDetails
{
    public function __construct(
        public string $consultationId,
        public string $clinicId,
    ) {
    }
}
