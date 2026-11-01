<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Query\SearchClinicListings;

use App\Context\ClinicListing\Application\Query\Shared\ClinicListingAddressView;

/**
 * A clinic of the directory as a search lists it. `id` is the listing id,
 * never the clinic's. `distanceM` only for a search around a point;
 * `nextAvailableAt` is always null in v1.
 */
final readonly class ClinicListingSummary
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?ClinicListingAddressView $address,
        public ?string $phone,
        public ?int $distanceM,
        public bool $onlineBooking,
        public array $tags,
        public bool $onKiveto = true,
        public ?string $nextAvailableAt = null,
    ) {
    }
}
