<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Domain\Repository;

use App\Context\ClinicListing\Domain\ClinicListing;

interface ClinicListingRepositoryInterface
{
    /** Inserts the projection, or rewrites the one of that clinic. */
    public function save(ClinicListing $listing): void;

    public function findByClinicId(string $clinicId): ?ClinicListing;
}
