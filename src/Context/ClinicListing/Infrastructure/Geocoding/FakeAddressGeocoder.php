<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Infrastructure\Geocoding;

use App\Context\ClinicListing\Application\Port\AddressGeocoderInterface;
use App\Context\ClinicListing\Domain\ListingAddress;
use App\Context\ClinicListing\Domain\ListingCoordinates;

/**
 * The geocoder of the dev and test environments (bound under when@dev and
 * when@test): no network. A known city is placed at its centre, nudged by up
 * to ~2 km from a hash of the street so that two clinics of a city differ;
 * any other city is spread over metropolitan France from its name. A city
 * containing « introuvable » cannot be placed.
 */
final readonly class FakeAddressGeocoder implements AddressGeocoderInterface
{
    public const string UNKNOWN = 'introuvable';

    /** City (lower case) → [latitude, longitude] of its centre. */
    private const array CITIES = [
        'paris'      => [48.856614, 2.352222],
        'lyon'       => [45.764043, 4.835659],
        'marseille'  => [43.296482, 5.369780],
        'cassis'     => [43.214036, 5.539632],
        'bordeaux'   => [44.837789, -0.579180],
        'lille'      => [50.629250, 3.057256],
        'toulouse'   => [43.604652, 1.444209],
        'nantes'     => [47.218371, -1.553621],
        'nice'       => [43.710173, 7.261953],
        'strasbourg' => [48.573405, 7.752111],
    ];

    public function geocode(ListingAddress $address): ?ListingCoordinates
    {
        $city = mb_strtolower($address->city());

        if (str_contains($city, self::UNKNOWN)) {
            return null;
        }

        [$latitude, $longitude] = self::CITIES[$city] ?? [
            42.5 + self::fraction($city, 0) * 8.0,
            -4.0 + self::fraction($city, 4) * 11.5,
        ];

        $street = $address->line();

        return ListingCoordinates::fromDegrees(
            $latitude + (self::fraction($street, 0) - 0.5) * 0.04,
            $longitude + (self::fraction($street, 4) - 0.5) * 0.04,
        );
    }

    /** A stable number in [0, 1) drawn from the text. */
    private static function fraction(string $text, int $offset): float
    {
        return hexdec(mb_substr(hash('sha256', $text), $offset * 2, 8)) / 0x100000000;
    }
}
