<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Infrastructure\Geocoding;

use App\Context\ClinicListing\Application\Port\AddressGeocoderInterface;
use App\Context\ClinicListing\Domain\ListingAddress;
use App\Context\ClinicListing\Domain\ListingCoordinates;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The Base Adresse Nationale through the Géoplateforme geocoder (the search
 * endpoint, not its autocompletion): the best match of the address, kept
 * only when the service is confident enough (score ≥ MIN_SCORE). Its
 * GeoJSON point is [longitude, latitude].
 */
final readonly class GeoplateformeAddressGeocoder implements AddressGeocoderInterface
{
    private const string SERVICE  = 'Géoplateforme geocoder';
    private const string ENDPOINT = 'https://data.geopf.fr/geocodage/search';
    private const float TIMEOUT   = 5.0;
    private const float MIN_SCORE = 0.5;

    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    public function geocode(ListingAddress $address): ?ListingCoordinates
    {
        $query = ['q' => $address->toQuery(), 'index' => 'address', 'limit' => 1];

        if (null !== $address->postalCode()) {
            $query['postcode'] = $address->postalCode();
        }

        try {
            $response = $this->httpClient->request('GET', self::ENDPOINT, [
                'query'   => $query,
                'timeout' => self::TIMEOUT,
                'headers' => ['Accept' => 'application/json'],
            ]);
            $status = $response->getStatusCode();

            if (429 === $status) {
                throw ExternalServiceUnavailable::rateLimited(self::SERVICE);
            }

            if (200 !== $status) {
                throw ExternalServiceUnavailable::failed(self::SERVICE, $status);
            }

            $body = $response->toArray();
        } catch (ExceptionInterface $e) {
            throw ExternalServiceUnavailable::unreachable(self::SERVICE, $e);
        }

        return self::bestMatch($body);
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private static function bestMatch(array $body): ?ListingCoordinates
    {
        $features    = \is_array($body['features'] ?? null) ? $body['features'] : [];
        $feature     = \is_array($features[0] ?? null) ? $features[0] : [];
        $properties  = \is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
        $geometry    = \is_array($feature['geometry'] ?? null) ? $feature['geometry'] : [];
        $coordinates = \is_array($geometry['coordinates'] ?? null) ? $geometry['coordinates'] : [];
        $score       = $properties['score'] ?? null;
        $longitude   = $coordinates[0] ?? null;
        $latitude    = $coordinates[1] ?? null;

        if (!is_numeric($score) || (float) $score < self::MIN_SCORE || !is_numeric($longitude) || !is_numeric($latitude)) {
            return null;
        }

        return ListingCoordinates::fromDegrees((float) $latitude, (float) $longitude);
    }
}
