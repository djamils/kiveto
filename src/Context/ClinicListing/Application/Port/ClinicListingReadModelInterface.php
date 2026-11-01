<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Port;

/**
 * The directory as the Portal searches it: only the listings that are active
 * and whose clinic chose to be listed. `$q`, when given, matches the name or
 * the city (contains, `%` and `_` taken literally).
 */
interface ClinicListingReadModelInterface
{
    /**
     * By name then id, after the given position.
     *
     * @return list<ListingRow>
     */
    public function searchByName(?string $q, ?string $afterName, ?string $afterId, int $limit): array;

    /**
     * Within `$radiusM` metres of the point, nearest first then by id, after
     * the given position; listings without coordinates are left out.
     *
     * @return list<ListingRow> with their distance
     */
    public function searchByDistance(?string $q, float $latitude, float $longitude, int $radiusM, ?int $afterDistanceM, ?string $afterId, int $limit): array;

    /** A listing that is listed and active, null otherwise (or for a malformed id). */
    public function findListed(string $listingId): ?ListingRow;
}
