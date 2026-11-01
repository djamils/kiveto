<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Query\Shared;

final readonly class ClinicListingAddressView
{
    public function __construct(
        public string $line,
        public ?string $postalCode,
        public string $city,
    ) {
    }

    /** Null unless both the street line and the city are known. */
    public static function of(?string $line, ?string $postalCode, ?string $city): ?self
    {
        return null !== $line && null !== $city ? new self($line, $postalCode, $city) : null;
    }
}
