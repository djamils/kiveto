<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Port;

use App\Context\ClinicListing\Domain\ListingAddress;
use App\Context\ClinicListing\Domain\ListingCoordinates;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;

/** Places an address on the map. Calls a remote service: never inside a transaction. */
interface AddressGeocoderInterface
{
    /**
     * Null when the service answered but could not place the address.
     *
     * @throws ExternalServiceUnavailable when the service cannot answer (retried)
     */
    public function geocode(ListingAddress $address): ?ListingCoordinates;
}
