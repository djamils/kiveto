<?php

declare(strict_types=1);

namespace App\System\Money\Infrastructure\ExchangeRate;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Application\Port\ExchangeRateProvider;
use App\System\Money\Domain\Exception\ExchangeRateNotFoundException;
use App\System\Money\Domain\ValueObject\ExchangeRatePair;
use App\System\Money\Domain\ValueObject\HistoricalRate;

final class FixedExchangeRateProvider implements ExchangeRateProvider
{
    /** @param array<string, string> $rates keyed by "FROM/TO" e.g. "EUR/CHF" => "0.9523" */
    public function __construct(private readonly array $rates)
    {
    }

    public function getCurrentRate(ExchangeRatePair $pair): HistoricalRate
    {
        return $this->getRateAt($pair, new \DateTimeImmutable());
    }

    public function getRateAt(ExchangeRatePair $pair, \DateTimeImmutable $date): HistoricalRate
    {
        $key = $pair->toString();

        if (!isset($this->rates[$key])) {
            throw new ExchangeRateNotFoundException($pair->from()->toString(), $pair->to()->toString(), $date->format('Y-m-d'));
        }

        return HistoricalRate::of($pair, $this->rates[$key], $date, 'FIXED');
    }

    /** @return list<HistoricalRate> */
    public function fetchSnapshot(\DateTimeImmutable $date): array
    {
        $result = [];

        foreach ($this->rates as $key => $rate) {
            [$fromStr, $toStr] = explode('/', $key);
            $pair              = ExchangeRatePair::of(
                CurrencyCode::fromString($fromStr),
                CurrencyCode::fromString($toStr),
            );
            $result[] = HistoricalRate::of($pair, $rate, $date, 'FIXED');
        }

        return $result;
    }
}
