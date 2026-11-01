<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * A listed clinic, as a new-client booking needs it. The clinic id never
 * leaves the server: the owner only ever sees the listing id.
 */
final readonly class ListingForBooking
{
    /**
     * @param bool $onlineBooking the clinic takes new clients online now
     */
    public function __construct(
        public string $listingId,
        public string $clinicId,
        public bool $onlineBooking,
    ) {
    }
}
