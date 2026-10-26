<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Port;

use App\Shared\Application\Document\DocumentClinic;

/**
 * The clinic as an ordonnance prints it (name, letterhead, logo), read from
 * the Clinic context; null for a clinic it does not know.
 */
interface ClinicDocumentIdentityInterface
{
    public function forClinic(string $clinicId): ?DocumentClinic;
}
