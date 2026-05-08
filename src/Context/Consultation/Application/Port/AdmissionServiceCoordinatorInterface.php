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
}
