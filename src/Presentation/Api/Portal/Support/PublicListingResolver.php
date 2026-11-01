<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Support;

use App\Context\ClinicListing\Application\Query\GetClinicListing\ClinicListingDetail;
use App\Context\ClinicListing\Application\Query\GetClinicListing\GetClinicListing;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Shared\Problem\ApiProblem;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * The gate of every `/clinic-listings/{l}…` route: an unknown, unlisted or
 * inactive listing is `404 not_found`; the booking routes also need the
 * listing open to new clients online (`onlineBooking`), else `422
 * booking_not_open`. The clinic id it resolves stays on the server.
 */
final readonly class PublicListingResolver
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    /**
     * @throws ApiProblem 404
     */
    public function listing(string $listingId): ClinicListingDetail
    {
        $listing = $this->queryBus->ask(new GetClinicListing($listingId));

        if (!$listing instanceof ClinicListingDetail) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        }

        return $listing;
    }

    /**
     * The clinic id behind a listing open to new clients online.
     *
     * @throws ApiProblem 404, 422
     */
    public function bookableClinicId(string $listingId): string
    {
        $listing = $this->listing($listingId);

        if (!$listing->onlineBooking) {
            throw PortalProblemMap::problem(PortalProblemMap::BOOKING_NOT_OPEN);
        }

        return $listing->clinicId;
    }
}
