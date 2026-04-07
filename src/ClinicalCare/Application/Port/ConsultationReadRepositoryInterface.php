<?php

declare(strict_types=1);

namespace App\ClinicalCare\Application\Port;

use App\ClinicalCare\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\ClinicalCare\Domain\ValueObject\ConsultationId;

interface ConsultationReadRepositoryInterface
{
    /**
     * Finds a consultation with all its details.
     *
     * @throws \DomainException if consultation not found
     */
    public function findById(ConsultationId $consultationId): ConsultationDetailsDTO;
}
