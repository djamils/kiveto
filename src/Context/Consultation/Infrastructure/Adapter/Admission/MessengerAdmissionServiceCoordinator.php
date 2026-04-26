<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Adapter\Admission;

use App\Context\Consultation\Application\Port\AdmissionServiceCoordinatorInterface;

final readonly class MessengerAdmissionServiceCoordinator implements AdmissionServiceCoordinatorInterface
{
    public function updateLocationStatus(
        string $admissionId,
        string $newLocationStatus,
        string $triggeredByUserId,
    ): void {
        // TODO: Admission BC will expose an UpdateAdmissionLocationStatus command in a future story.
        // Until then this is a no-op stub so the Consultation flow does not block.
    }
}
