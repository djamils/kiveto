<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Command\GeocodeClinicListing;

use App\Shared\Application\Bus\ExternalApiCommandInterface;

/**
 * Geocodes the current address of the clinic's listing (`async_external`,
 * Messenger's 3 retries), then records the answer with AttachListingCoordinates.
 */
final readonly class GeocodeClinicListing implements ExternalApiCommandInterface
{
    public function __construct(public string $clinicId)
    {
    }
}
