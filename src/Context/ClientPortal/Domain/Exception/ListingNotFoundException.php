<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/** The directory listing is unknown, malformed, not listed or inactive (a 404). */
final class ListingNotFoundException extends \DomainException
{
    public function __construct(public readonly string $listingId)
    {
        parent::__construct(\sprintf('Listing "%s" is not listed.', $listingId));
    }
}
