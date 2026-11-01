<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/** `limiter.portal_listing_booking`: the account made its 10 new-client requests of the last 24 hours. */
final class ListingBookingRateLimitedException extends \DomainException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Trop de demandes de rendez-vous envoyées. Réessayez plus tard.');
    }
}
