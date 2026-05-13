<?php

declare(strict_types=1);

namespace App\System\Money\Application\Port;

use App\System\Money\Domain\ValueObject\ExchangeRatePair;
use App\System\Money\Domain\ValueObject\HistoricalRate;

interface ExchangeRateProvider
{
    public function getCurrentRate(ExchangeRatePair $pair): HistoricalRate;

    public function getRateAt(ExchangeRatePair $pair, \DateTimeImmutable $date): HistoricalRate;

    /** @return list<HistoricalRate> */
    public function fetchSnapshot(\DateTimeImmutable $date): array;
}
