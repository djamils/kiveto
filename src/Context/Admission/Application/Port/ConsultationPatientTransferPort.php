<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Port;

interface ConsultationPatientTransferPort
{
    /**
     * Moves the consultations of a merged patient record onto the surviving
     * record, via the Consultation BC.
     */
    public function transferConsultations(string $clinicId, string $sourcePatientId, string $targetPatientId): void;
}
