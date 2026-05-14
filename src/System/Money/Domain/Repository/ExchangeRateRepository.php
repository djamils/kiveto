<?php

declare(strict_types=1);

namespace App\System\Money\Domain\Repository;

use App\System\Money\Domain\ExchangeRate;
use App\System\Money\Domain\ValueObject\ExchangeRatePair;

/**
 * Write repository for the ExchangeRate aggregate.
 *
 * findRateAt() returns the most recent rate whose effective date is ≤ $date
 * (ORDER BY effective_date DESC LIMIT 1). Used by ConversionService.
 */
interface ExchangeRateRepository
{
    public function save(ExchangeRate $exchangeRate): void;

    public function findRateAt(ExchangeRatePair $pair, \DateTimeImmutable $date): ?ExchangeRate;
}
