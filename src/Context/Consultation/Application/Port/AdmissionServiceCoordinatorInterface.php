<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

interface AdmissionServiceCoordinatorInterface
{
    /**
     * Update the location status of an admission
     * (e.g. to IN_CONSULTATION when a consultation starts).
     */
    public function updateLocationStatus(
        string $admissionId,
        string $newLocationStatus,
        string $clinicId,
    ): void;

    /**
     * Ends the visit the consultation belonged to, so the patient leaves the
     * board and lands in the discharged column.
     *
     * A no-op when the visit is already over: several consultations can share
     * one admission and only the first of them ends it.
     */
    public function closeAdmission(
        string $admissionId,
        string $clinicId,
        string $closureReason,
    ): void;
}
