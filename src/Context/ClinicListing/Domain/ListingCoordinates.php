<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Domain;

use App\Context\ClinicListing\Domain\Exception\InvalidListingCoordinatesException;

/**
 * Where a clinic lies, in WGS 84 degrees, as the geocoder placed its address.
 * Kept to six decimals (about 10 cm), the precision the projection stores.
 */
final readonly class ListingCoordinates
{
    private float $latitude;
    private float $longitude;

    private function __construct(float $latitude, float $longitude)
    {
        $this->latitude  = $latitude;
        $this->longitude = $longitude;
    }

    public static function fromDegrees(float $latitude, float $longitude): self
    {
        if ($latitude < -90.0 || $latitude > 90.0 || $longitude < -180.0 || $longitude > 180.0) {
            throw new InvalidListingCoordinatesException($latitude, $longitude);
        }

        return new self(round($latitude, 6), round($longitude, 6));
    }

    public function latitude(): float
    {
        return $this->latitude;
    }

    public function longitude(): float
    {
        return $this->longitude;
    }

    public function equals(self $other): bool
    {
        return $this->latitude === $other->latitude && $this->longitude === $other->longitude;
    }
}
