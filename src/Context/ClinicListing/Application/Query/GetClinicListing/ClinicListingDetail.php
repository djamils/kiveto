<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Query\GetClinicListing;

use App\Context\ClinicListing\Application\Query\Shared\ClinicListingAddressView;

/**
 * A clinic of the directory, in full. `clinicId` resolves the listing for
 * the new-client booking and must never leave the server: the API exposes
 * the listing `id` only.
 */
final readonly class ClinicListingDetail
{
    /**
     * @param list<OpeningHoursView> $openingHours by day, then opening time
     * @param list<string>           $tags
     */
    public function __construct(
        public string $id,
        public string $clinicId,
        public string $name,
        public ?ClinicListingAddressView $address,
        public ?string $phone,
        public string $timezone,
        public array $openingHours,
        public bool $acceptsNewClients,
        public bool $onlineBooking,
        public array $tags,
    ) {
    }
}
