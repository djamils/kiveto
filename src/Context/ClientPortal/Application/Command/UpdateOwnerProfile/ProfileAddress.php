<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\UpdateOwnerProfile;

/** A postal address as the owner typed it; no geocoding. */
final readonly class ProfileAddress
{
    /**
     * @param string $countryCode ISO 3166-1 alpha-2 (« FR »)
     */
    public function __construct(
        public string $streetLine1,
        public ?string $postalCode,
        public string $city,
        public string $countryCode,
    ) {
    }
}
