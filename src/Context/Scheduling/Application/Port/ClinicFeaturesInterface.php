<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

/**
 * What Kiveto has switched on for a clinic, among the features Scheduling
 * serves (the console's feature catalogue).
 */
interface ClinicFeaturesInterface
{
    /** « Prise de RDV en ligne »: off, the clinic takes no booking from the Portal. */
    public function onlineBookingOn(string $clinicId): bool;

    /** « Rappels SMS »: off, no reminder is sent. */
    public function smsRemindersOn(string $clinicId): bool;
}
