<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Port;

/**
 * The time zone a clinic counts its days in (Clinic's settings).
 */
interface ClinicTimeZoneInterface
{
    public function of(string $clinicId): \DateTimeZone;
}
