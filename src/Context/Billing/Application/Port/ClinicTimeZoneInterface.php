<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

/**
 * The clinic's time zone, in which messages date a visit (« la consultation
 * de Minou du 16 octobre 2026 »). Invoice dates stay fiscal (Europe/Paris,
 * as the invoice prints them).
 */
interface ClinicTimeZoneInterface
{
    /** Europe/Paris for a clinic the Clinic context does not know. */
    public function of(string $clinicId): \DateTimeZone;
}
