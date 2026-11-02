<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Domain\Repository;

use App\Context\ClinicListing\Domain\ClinicListing;

interface ClinicListingRepositoryInterface
{
    /**
     * Inserts the projection, or rewrites the one of that clinic — except
     * its coordinates, written by saveCoordinates() only (a refresh never
     * overwrites coordinates attached since it read the listing); they are
     * dropped with the address only.
     */
    public function save(ClinicListing $listing): void;

    /**
     * Writes the listing's coordinates, provided its row still has the same
     * address hash (an answer for an address changed since is not written).
     */
    public function saveCoordinates(ClinicListing $listing): void;

    public function findByClinicId(string $clinicId): ?ClinicListing;
}
