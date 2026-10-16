<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure\Adapter\AddressDirectory;

use App\Context\Client\Application\Port\AddressSuggestion;
use App\Context\Client\Infrastructure\Adapter\AddressDirectory\GeoplateformeAddressDirectory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GeoplateformeAddressDirectoryTest extends TestCase
{
    public function testSuggestsTheAddressesNearThePostalCode(): void
    {
        $urls   = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $url;

            return str_starts_with($url, 'https://geo.api.gouv.fr/communes')
                ? new MockResponse((string) json_encode([['nom' => 'Paris', 'centre' => ['type' => 'Point', 'coordinates' => [2.347, 48.8589]]]]))
                : new MockResponse((string) json_encode(['features' => [
                    ['properties' => ['name' => '12 Rue de Rome', 'postcode' => '75008', 'city' => 'Paris']],
                    ['properties' => ['name' => 'Rue sans ville']],
                    'nonsense',
                ]]));
        });
        $directory = new GeoplateformeAddressDirectory($client, new ArrayAdapter());

        $found = $directory->suggest('12 rue de rome', 6, '75011');
        self::assertCount(1, $found);
        self::assertInstanceOf(AddressSuggestion::class, $found[0]);
        self::assertSame(['12 Rue de Rome', '75008', 'Paris'], [$found[0]->street, $found[0]->postalCode, $found[0]->city]);
        // The centre of the postal code is kept: the second lookup asks the geocoder only.
        $directory->suggest('12 rue de rome', 6, '75011');

        self::assertCount(3, $urls);
        self::assertStringContainsString('codePostal=75011', $urls[0]);
        self::assertStringContainsString('lon=2.347', $urls[1]);
        self::assertStringContainsString('lat=48.8589', $urls[1]);
        self::assertStringContainsString('autocomplete=1', $urls[1]);
        self::assertStringStartsWith('https://data.geopf.fr/geocodage/search', $urls[2]);
    }

    public function testSuggestsWithoutBiasWhenThePostalCodeHasNoCentre(): void
    {
        $urls   = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $url;

            return str_starts_with($url, 'https://geo.api.gouv.fr/communes')
                ? new MockResponse('[]')
                : new MockResponse('{"features": "nonsense"}');
        });

        self::assertSame([], (new GeoplateformeAddressDirectory($client, new ArrayAdapter()))->suggest('rue de rome', 6, '99999'));
        self::assertStringNotContainsString('lat=', $urls[1]);
    }

    public function testSuggestsNothingWhenTheGeocoderCannotBeReached(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 503]));

        self::assertSame([], (new GeoplateformeAddressDirectory($client, new ArrayAdapter()))->suggest('rue de rome', 6));
    }

    public function testListsTheCommunesOfAPostalCodeOnceADay(): void
    {
        $calls  = 0;
        $client = new MockHttpClient(static function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse((string) json_encode([['nom' => 'La Ciotat'], ['nom' => 'Ceyreste'], ['nom' => 'La Ciotat'], ['code' => '13000'], 'nonsense']));
        });
        $directory = new GeoplateformeAddressDirectory($client, new ArrayAdapter());

        self::assertSame(['Ceyreste', 'La Ciotat'], $directory->communesOf('13600'));
        self::assertSame(['Ceyreste', 'La Ciotat'], $directory->communesOf('13600'));
        self::assertSame(1, $calls);
    }

    public function testSaysWhenTheCommunesCannotBeReached(): void
    {
        $client = new MockHttpClient(new MockResponse('not json'));

        self::assertNull((new GeoplateformeAddressDirectory($client, new ArrayAdapter()))->communesOf('13600'));
    }
}
