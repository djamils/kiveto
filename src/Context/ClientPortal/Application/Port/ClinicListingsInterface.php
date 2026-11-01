<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** The clinic directory (ClinicListing context): which clinic a listing is. */
interface ClinicListingsInterface
{
    /** A listed, active listing; null when unknown, malformed, unlisted or inactive. */
    public function listing(string $listingId): ?ListingForBooking;
}
