<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

use App\Context\ClientPortal\Domain\Exception\ListingBookingRateLimitedException;

/**
 * `limiter.portal_listing_booking`: 10 new-client booking requests per
 * account per 24 hours, kept outside any transaction.
 */
interface ListingBookingQuotaInterface
{
    /** @throws ListingBookingRateLimitedException when the account used its 10 requests */
    public function consume(string $userId): void;
}
