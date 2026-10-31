<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

use App\Shared\Application\Document\DocumentClinic;

/** How a clinic presents itself to its clients: name, city, time zone, letterhead (Clinic context). */
interface ClinicProfileInterface
{
    /** An unknown clinic answers an empty name, no city and UTC. */
    public function clinicOf(string $clinicId): ClinicLabel;

    /** The clinic as its documents print it (name, letterhead, logo), or null when unknown. */
    public function documentHeaderOf(string $clinicId): ?DocumentClinic;
}
