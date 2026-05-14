<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Money\Infrastructure\ExchangeRate;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\Exception\ExchangeRateNotFoundException;
use App\System\Money\Domain\ValueObject\ExchangeRatePair;
use App\System\Money\Domain\ValueObject\HistoricalRate;
use App\System\Money\Infrastructure\ExchangeRate\EcbExchangeRateProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class EcbExchangeRateProviderTest extends TestCase
{
    private const string SAMPLE_XML = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <gesmes:Envelope xmlns:gesmes="http://www.gesmes.org/xml/2002-08-01" xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref">
            <Cube>
                <Cube time="2026-05-14">
                    <Cube currency="USD" rate="1.0850"/>
                    <Cube currency="CHF" rate="0.9523"/>
                    <Cube currency="GBP" rate="0.8600"/>
                </Cube>
            </Cube>
        </gesmes:Envelope>
        XML;

    public function testFetchSnapshotParsesXmlAndReturnsRates(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(self::SAMPLE_XML, ['http_code' => 200]));
        $provider   = new EcbExchangeRateProvider($httpClient);

        $rates = $provider->fetchSnapshot(new \DateTimeImmutable('2026-05-14'));

        self::assertCount(3, $rates);

        foreach ($rates as $rate) {
            self::assertInstanceOf(HistoricalRate::class, $rate);
            self::assertSame('ECB', $rate->source());
            self::assertSame('EUR', $rate->pair()->from()->toString());
            self::assertSame('2026-05-14', $rate->effectiveDate()->format('Y-m-d'));
        }
    }

    public function testGetCurrentRateReturnsMatchingRate(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(self::SAMPLE_XML, ['http_code' => 200]));
        $provider   = new EcbExchangeRateProvider($httpClient);

        $pair = ExchangeRatePair::of(
            CurrencyCode::fromString('EUR'),
            CurrencyCode::fromString('USD'),
        );

        $rate = $provider->getCurrentRate($pair);

        self::assertSame('1.0850', $rate->rate());
        self::assertSame('EUR/USD', $rate->pair()->toString());
    }

    public function testGetRateAtReturnsMatchingRate(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(self::SAMPLE_XML, ['http_code' => 200]));
        $provider   = new EcbExchangeRateProvider($httpClient);

        $pair = ExchangeRatePair::of(
            CurrencyCode::fromString('EUR'),
            CurrencyCode::fromString('CHF'),
        );

        $rate = $provider->getRateAt($pair, new \DateTimeImmutable('2026-05-14'));

        self::assertSame('0.9523', $rate->rate());
        self::assertSame('EUR/CHF', $rate->pair()->toString());
    }

    public function testGetCurrentRateThrowsForUnknownPair(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(self::SAMPLE_XML, ['http_code' => 200]));
        $provider   = new EcbExchangeRateProvider($httpClient);

        $pair = ExchangeRatePair::of(
            CurrencyCode::fromString('EUR'),
            CurrencyCode::fromString('JPY'),
        );

        $this->expectException(ExchangeRateNotFoundException::class);

        $provider->getCurrentRate($pair);
    }
}
