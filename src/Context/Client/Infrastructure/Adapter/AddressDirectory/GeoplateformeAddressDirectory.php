<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Adapter\AddressDirectory;

use App\Context\Client\Application\Port\AddressDirectoryInterface;
use App\Context\Client\Application\Port\AddressSuggestion;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The French public address services: the Base Adresse Nationale through the
 * Géoplateforme geocoder for the addresses, the official geographic API for
 * the communes of a postal code (and where a postal code lies, to put the
 * addresses near the clinic first). Postal codes change rarely: what they
 * serve is kept a day.
 */
final readonly class GeoplateformeAddressDirectory implements AddressDirectoryInterface
{
    private const string GEOCODER = 'https://data.geopf.fr/geocodage/search';
    private const string COMMUNES = 'https://geo.api.gouv.fr/communes';
    private const float TIMEOUT   = 3.0;
    private const int CACHE_TTL   = 86400;

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
    ) {
    }

    public function suggest(string $query, int $limit, ?string $nearPostalCode = null): array
    {
        $parameters = ['q' => $query, 'limit' => $limit, 'autocomplete' => 1, 'index' => 'address'];
        $centre     = null === $nearPostalCode ? null : $this->centreOf($nearPostalCode);

        if (null !== $centre) {
            [$parameters['lon'], $parameters['lat']] = $centre;
        }

        $features = $this->json(self::GEOCODER, $parameters)['features'] ?? null;

        $suggestions = [];
        foreach (\is_array($features) ? $features : [] as $feature) {
            $properties = \is_array($feature) && \is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $street     = $properties['name'] ?? null;
            $postalCode = $properties['postcode'] ?? null;
            $city       = $properties['city'] ?? null;

            if (\is_string($street) && \is_string($postalCode) && \is_string($city)) {
                $suggestions[] = new AddressSuggestion($street, $postalCode, $city);
            }
        }

        return $suggestions;
    }

    public function communesOf(string $postalCode): ?array
    {
        return $this->cache->get('client.communes.' . $postalCode, function (ItemInterface $item) use ($postalCode): ?array {
            $communes = $this->json(self::COMMUNES, ['codePostal' => $postalCode, 'fields' => 'nom']);

            if (null === $communes) {
                // Not reached: ask again next time rather than remember the failure.
                $item->expiresAfter(1);

                return null;
            }

            $item->expiresAfter(self::CACHE_TTL);

            $names = [];
            foreach ($communes as $commune) {
                $name = \is_array($commune) ? ($commune['nom'] ?? null) : null;
                if (\is_string($name)) {
                    $names[] = $name;
                }
            }

            $names = array_values(array_unique($names));
            sort($names);

            return $names;
        });
    }

    /**
     * Longitude and latitude of the first commune the postal code serves.
     *
     * @return array{0: float, 1: float}|null
     */
    private function centreOf(string $postalCode): ?array
    {
        return $this->cache->get('client.postcode-centre.' . $postalCode, function (ItemInterface $item) use ($postalCode): ?array {
            $communes    = $this->json(self::COMMUNES, ['codePostal' => $postalCode, 'fields' => 'centre']);
            $first       = \is_array($communes[0] ?? null) ? $communes[0] : [];
            $centre      = \is_array($first['centre'] ?? null) ? $first['centre'] : [];
            $coordinates = \is_array($centre['coordinates'] ?? null) ? $centre['coordinates'] : [];
            $longitude   = $coordinates[0] ?? null;
            $latitude    = $coordinates[1] ?? null;

            if (!is_numeric($longitude) || !is_numeric($latitude)) {
                $item->expiresAfter(null === $communes ? 1 : self::CACHE_TTL);

                return null;
            }

            $item->expiresAfter(self::CACHE_TTL);

            return [(float) $longitude, (float) $latitude];
        });
    }

    /**
     * @param array<string, string|int|float> $query
     *
     * @return array<array-key, mixed>|null null when the service cannot be reached or answers nonsense
     */
    private function json(string $url, array $query): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'query'   => $query,
                'timeout' => self::TIMEOUT,
                'headers' => ['Accept' => 'application/json'],
            ]);

            return $response->toArray();
        } catch (ExceptionInterface) {
            return null;
        }
    }
}
