<?php

declare(strict_types=1);

namespace App\System\Money\Infrastructure\ExchangeRate;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Application\Port\ExchangeRateProvider;
use App\System\Money\Domain\Exception\ExchangeRateNotFoundException;
use App\System\Money\Domain\ValueObject\ExchangeRatePair;
use App\System\Money\Domain\ValueObject\HistoricalRate;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class EcbExchangeRateProvider implements ExchangeRateProvider
{
    private const string ECB_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public function getCurrentRate(ExchangeRatePair $pair): HistoricalRate
    {
        $rates = $this->fetchSnapshot(new \DateTimeImmutable());

        foreach ($rates as $rate) {
            if ($rate->pair()->equals($pair)) {
                return $rate;
            }
        }

        throw new ExchangeRateNotFoundException(
            $pair->from()->toString(),
            $pair->to()->toString(),
            (new \DateTimeImmutable())->format('Y-m-d'),
        );
    }

    public function getRateAt(ExchangeRatePair $pair, \DateTimeImmutable $date): HistoricalRate
    {
        $rates = $this->fetchSnapshot($date);

        foreach ($rates as $rate) {
            if ($rate->pair()->equals($pair)) {
                return $rate;
            }
        }

        throw new ExchangeRateNotFoundException(
            $pair->from()->toString(),
            $pair->to()->toString(),
            $date->format('Y-m-d'),
        );
    }

    /** @return list<HistoricalRate> */
    public function fetchSnapshot(\DateTimeImmutable $date): array
    {
        $response = $this->httpClient->request('GET', self::ECB_URL);
        $xml      = new \SimpleXMLElement($response->getContent());

        $eur           = CurrencyCode::fromString('EUR');
        $cubeContainer = $xml->Cube->Cube ?? null;

        if (null === $cubeContainer) {
            return [];
        }

        $effectiveDate = isset($cubeContainer['time'])
            ? new \DateTimeImmutable((string) $cubeContainer['time'])
            : $date;

        $rates = [];

        foreach ($cubeContainer->Cube as $cube) {
            $currencyStr = (string) ($cube['currency'] ?? '');
            $rateStr     = (string) ($cube['rate'] ?? '');

            if ('' === $currencyStr || '' === $rateStr) {
                continue;
            }

            try {
                $targetCode = CurrencyCode::fromString($currencyStr);
            } catch (\InvalidArgumentException) {
                continue;
            }

            $pair    = ExchangeRatePair::of($eur, $targetCode);
            $rates[] = HistoricalRate::of($pair, $rateStr, $effectiveDate, 'ECB');
        }

        return $rates;
    }
}
