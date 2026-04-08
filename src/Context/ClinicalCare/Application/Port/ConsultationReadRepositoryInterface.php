<?php

declare(strict_types=1);

namespace App\Context\ClinicalCare\Application\Port;

use App\Context\ClinicalCare\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\ClinicalCare\Domain\ValueObject\ConsultationId;

interface ConsultationReadRepositoryInterface
{
    /**
     * Finds a consultation with all its details.
     *
     * @throws \DomainException if consultation not found
     */
    public function findById(ConsultationId $consultationId): ConsultationDetailsDTO;
}
