<?php

declare(strict_types=1);

namespace App\System\Money\Domain\Repository;

use App\System\Money\Domain\ExchangeRate;
use App\System\Money\Domain\ValueObject\ExchangeRatePair;

interface ExchangeRateRepository
{
    public function save(ExchangeRate $exchangeRate): void;

    public function findRateAt(ExchangeRatePair $pair, \DateTimeImmutable $date): ?ExchangeRate;
}
