<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Port;

interface PatientDirectoryInterface
{
    /**
     * The animal treated by a consultation and the person who answers for it.
     *
     * Returns null for a consultation whose patient was never identified.
     */
    public function forConsultation(string $consultationId, string $clinicId): ?PatientSnapshotDto;
}
