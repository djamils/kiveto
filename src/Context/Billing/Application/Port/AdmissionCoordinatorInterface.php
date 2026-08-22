<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

interface AdmissionCoordinatorInterface
{
    /**
     * Ends the visit once it has been paid for.
     *
     * Billing does not own the admission lifecycle, so this goes through the
     * Admission BC rather than writing its table.
     */
    public function closeAfterCheckout(string $admissionId, string $clinicId): void;
}
