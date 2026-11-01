<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/**
 * The listed clinic takes no new client online (`onlineBooking` false: it
 * does not accept new clients, or Scheduling takes no new-client request).
 */
final class ListingBookingNotOpenException extends \DomainException
{
    public function __construct(public readonly string $listingId)
    {
        parent::__construct(\sprintf('Listing "%s" takes no new client online.', $listingId));
    }
}
