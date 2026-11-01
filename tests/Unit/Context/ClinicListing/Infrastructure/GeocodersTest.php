<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClinicListing\Infrastructure;

use App\Context\ClinicListing\Domain\ListingAddress;
use App\Context\ClinicListing\Infrastructure\Geocoding\FakeAddressGeocoder;
use App\Context\ClinicListing\Infrastructure\Geocoding\GeoplateformeAddressGeocoder;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GeocodersTest extends TestCase
{
    public function testTheGeoplateformeSearchEndpointPlacesTheAddress(): void
    {
        $urls   = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $url;

            return new MockResponse((string) json_encode(['features' => [[
                'geometry'   => ['type' => 'Point', 'coordinates' => [2.338123, 48.884612]],
                'properties' => ['score' => 0.92, 'label' => '18 Rue des Abbesses 75018 Paris'],
            ]]]));
        });

        $found = new GeoplateformeAddressGeocoder($client)->geocode(self::address('75018'));

        self::assertNotNull($found);
        self::assertSame([48.884612, 2.338123], [$found->latitude(), $found->longitude()]);
        self::assertStringStartsWith('https://data.geopf.fr/geocodage/search?', $urls[0]);
        self::assertStringContainsString('q=18%20rue%20des%20Abbesses%2075018%20Paris', $urls[0]);
        self::assertStringContainsString('postcode=75018', $urls[0]);
        self::assertStringContainsString('index=address', $urls[0]);
        self::assertStringNotContainsString('autocomplete', $urls[0]);
    }

    public function testWithoutPostalCodeTheQueryIsTheStreetAndCity(): void
    {
        $urls   = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $url;

            return new MockResponse('{"features": []}');
        });

        self::assertNull(new GeoplateformeAddressGeocoder($client)->geocode(self::address(null)));
        self::assertStringNotContainsString('postcode', $urls[0]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAnUnsureOrMalformedAnswerPlacesNothingCases(): iterable
    {
        yield 'low score' => [(string) json_encode(['features' => [['geometry' => ['coordinates' => [2.3, 48.8]], 'properties' => ['score' => 0.3]]]])];
        yield 'no score' => [(string) json_encode(['features' => [['geometry' => ['coordinates' => [2.3, 48.8]], 'properties' => []]]])];
        yield 'no geometry' => [(string) json_encode(['features' => [['properties' => ['score' => 0.9]]]])];
        yield 'no features' => [(string) json_encode(['type' => 'FeatureCollection'])];
        yield 'not a feature' => [(string) json_encode(['features' => ['nonsense']])];
    }

    #[DataProvider('provideAnUnsureOrMalformedAnswerPlacesNothingCases')]
    public function testAnUnsureOrMalformedAnswerPlacesNothing(string $body): void
    {
        self::assertNull(new GeoplateformeAddressGeocoder(new MockHttpClient(new MockResponse($body)))->geocode(self::address('75018')));
    }

    /**
     * @return iterable<string, array{MockResponse, bool}>
     */
    public static function provideAnOutageIsThrownForARetryCases(): iterable
    {
        yield 'rate limited' => [new MockResponse('', ['http_code' => 429]), true];
        yield 'server error' => [new MockResponse('', ['http_code' => 503]), false];
        yield 'bad request' => [new MockResponse('', ['http_code' => 400]), false];
        yield 'not json' => [new MockResponse('<html>'), false];
        yield 'transport' => [new MockResponse((static function (): \Generator {
            yield new TransportException('Connection refused');
        })()), false];
    }

    #[DataProvider('provideAnOutageIsThrownForARetryCases')]
    public function testAnOutageIsThrownForARetry(MockResponse $response, bool $throttled): void
    {
        try {
            new GeoplateformeAddressGeocoder(new MockHttpClient($response))->geocode(self::address('75018'));
            self::fail('The outage should be thrown.');
        } catch (ExternalServiceUnavailable $e) {
            self::assertSame($throttled, $e->isThrottled());
        }
    }

    public function testTheFakeGeocoderIsStableAndKnowsTheMainCities(): void
    {
        $geocoder = new FakeAddressGeocoder();
        $paris    = $geocoder->geocode(self::address('75018'));
        self::assertNotNull($paris);
        self::assertTrue($paris->equals($geocoder->geocode(self::address('75018')) ?? $paris));
        self::assertEqualsWithDelta(48.8566, $paris->latitude(), 0.03);
        self::assertEqualsWithDelta(2.3522, $paris->longitude(), 0.03);

        $elsewhere = $geocoder->geocode(ListingAddress::fromParts('1 place de la Mairie', '29000', 'Quimper') ?? self::address(null));
        self::assertNotNull($elsewhere);
        self::assertGreaterThanOrEqual(42.4, $elsewhere->latitude());
        self::assertLessThanOrEqual(50.6, $elsewhere->latitude());
        self::assertGreaterThanOrEqual(-4.1, $elsewhere->longitude());
        self::assertLessThanOrEqual(7.6, $elsewhere->longitude());

        self::assertNull($geocoder->geocode(ListingAddress::fromParts('1 rue Perdue', null, 'Ville-Introuvable') ?? self::address(null)));
    }

    private static function address(?string $postalCode): ListingAddress
    {
        $address = ListingAddress::fromParts('18 rue des Abbesses', $postalCode, 'Paris');
        \assert(null !== $address);

        return $address;
    }
}
