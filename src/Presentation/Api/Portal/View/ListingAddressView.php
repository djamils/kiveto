<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use App\Context\ClinicListing\Application\Query\Shared\ClinicListingAddressView;

/** A directory address: both letterhead street lines joined in `line`. */
final readonly class ListingAddressView
{
    public function __construct(
        public string $line,
        public ?string $postalCode,
        public string $city,
    ) {
    }

    public static function of(?ClinicListingAddressView $address): ?self
    {
        return null === $address ? null : new self($address->line, $address->postalCode, $address->city);
    }
}
