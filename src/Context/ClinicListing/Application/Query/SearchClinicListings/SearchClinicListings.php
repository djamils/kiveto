<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Query\SearchClinicListings;

use App\Context\ClinicListing\Domain\Exception\InvalidCursorException;
use App\Context\ClinicListing\Domain\Exception\InvalidListingSearchException;
use App\Shared\Application\Bus\QueryInterface;

/**
 * The public directory (S7c) → ClinicListingPage. Only listed, active
 * clinics. `q` (≥ 2 characters once trimmed, blank = none) matches the name
 * or the city. With `lat`/`lng` (both or neither): within RADIUS_METRES,
 * nearest first, clinics without coordinates left out; otherwise by name.
 * `cursor` is the `nextCursor` of the previous page, bound to the ordering
 * and the coordinates it was issued for.
 *
 * `limit` is clamped like every cursor route (Cursor::clampLimit: 20 by
 * default, 1 to 50).
 *
 * @throws InvalidListingSearchException (`q`, `lat`, `lng`)
 * @throws InvalidCursorException
 */
final readonly class SearchClinicListings implements QueryInterface
{
    public const int MIN_QUERY = 2;
    /** How far a search around a point reaches (bounding box, then Haversine). */
    public const int RADIUS_METRES = 150_000;

    public function __construct(
        public ?string $q = null,
        public ?float $lat = null,
        public ?float $lng = null,
        public ?string $cursor = null,
        public ?int $limit = null,
    ) {
    }
}
