<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\ClinicListing;

use App\Context\ClientPortal\Application\Port\ClinicListingsInterface;
use App\Context\ClientPortal\Application\Port\ListingForBooking;
use App\Context\ClinicListing\Application\Query\GetClinicListing\ClinicListingDetail;
use App\Context\ClinicListing\Application\Query\GetClinicListing\GetClinicListing;
use App\Shared\Application\Bus\QueryBusInterface;

/** ClinicListing's `GetClinicListing` (null when unknown, unlisted or inactive). */
final readonly class QueryBusClinicListings implements ClinicListingsInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function listing(string $listingId): ?ListingForBooking
    {
        $listing = $this->queryBus->ask(new GetClinicListing($listingId));

        return $listing instanceof ClinicListingDetail ? new ListingForBooking($listing->id, $listing->clinicId, $listing->onlineBooking) : null;
    }
}
