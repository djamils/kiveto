<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Domain\Repository;

use App\Context\ClinicListing\Domain\ListingSettings;

interface ListingSettingsRepositoryInterface
{
    /** The clinic's settings, or the defaults when it never saved any. */
    public function forClinic(string $clinicId): ListingSettings;

    public function save(ListingSettings $settings): void;
}
