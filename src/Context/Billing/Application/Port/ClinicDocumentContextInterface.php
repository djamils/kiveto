<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

/**
 * What the Clinic context says about a clinic that billing documents print:
 * its letterhead, and what it set about payment at the foot of its invoices.
 */
interface ClinicDocumentContextInterface
{
    public function forClinic(string $clinicId): ClinicDocumentContext;
}
