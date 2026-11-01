<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Port;

use App\Context\ClinicListing\Domain\ListingProfile;

/** What the Clinic context says of its clinics. */
interface ClinicProfileInterface
{
    /** The clinic as the directory shows it, null when Clinic does not know it. */
    public function profileOf(string $clinicId): ?ListingProfile;

    /**
     * Every clinic, whatever its status.
     *
     * @return list<string>
     */
    public function clinicIds(): array;
}
