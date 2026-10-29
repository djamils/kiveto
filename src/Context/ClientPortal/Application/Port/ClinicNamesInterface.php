<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** How a clinic is named to its clients (Clinic context). */
interface ClinicNamesInterface
{
    /** An unknown clinic answers an empty name and no city. */
    public function clinicOf(string $clinicId): ClinicLabel;
}
