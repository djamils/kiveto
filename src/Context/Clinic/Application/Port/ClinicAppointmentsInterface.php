<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Port;

/**
 * Scheduling, as the onboarding checklist asks it.
 */
interface ClinicAppointmentsInterface
{
    /** « Premier rendez-vous créé ». */
    public function hasAny(string $clinicId): bool;
}
