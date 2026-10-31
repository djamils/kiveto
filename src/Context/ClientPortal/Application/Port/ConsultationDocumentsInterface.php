<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** Consultation reports shared on the Portal (Consultation context). */
interface ConsultationDocumentsInterface
{
    /** Null when the consultation is unknown to the clinic. */
    public function shareability(string $clinicId, string $consultationId): ?ReportShareability;

    /** Renders the report as it is now under the given key (no database write). */
    public function renderReport(string $clinicId, string $consultationId, string $storageKey): void;

    /**
     * The consultations of a visit, oldest first.
     *
     * @return list<string>
     */
    public function consultationsOfAdmission(string $clinicId, string $admissionId): array;
}
