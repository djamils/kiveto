<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;

interface ConsultationReadRepositoryInterface
{
    /**
     * Finds a consultation with all its details.
     *
     * @throws \DomainException if consultation not found or does not belong to $clinicId
     */
    public function findById(ConsultationId $consultationId, ClinicId $clinicId): ConsultationDetailsDTO;
}
