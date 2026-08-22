<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\ListPrescriptionLinesForConsultation;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListPrescriptionLinesForConsultation implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $consultationId,
    ) {
    }
}
