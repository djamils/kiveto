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
     * Sends the patient back to the desk once the consultation is over, where
     * they wait to be charged before the visit is closed.
     *
     * A no-op unless the visit is running and the patient is still in the
     * consultation room: someone taken to surgery or to a ward has not
     * finished their visit, and neither has a closed one.
     */
    public function sendToReception(string $admissionId, string $clinicId): void;
}
