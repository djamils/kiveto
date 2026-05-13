<?php

declare(strict_types=1);

namespace App\System\Money\Domain\Exception;

final class StaleExchangeRateException extends \DomainException
{
    public function __construct(string $from, string $to, string $rateDate, string $requestedDate, int $maxDays)
    {
        parent::__construct(\sprintf(
            'Exchange rate for "%s/%s" dated "%s" is too stale (requested date: "%s", max staleness: %d days).',
            $from,
            $to,
            $rateDate,
            $requestedDate,
            $maxDays,
        ));
    }
}
