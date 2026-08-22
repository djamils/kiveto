<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Port;

interface ConsultationPrescriptionProviderInterface
{
    /**
     * The medicines recorded on a consultation, in the order they were added.
     *
     * @return list<ConsultationPrescriptionLineDto>
     */
    public function linesForConsultation(string $consultationId, string $clinicId): array;
}
