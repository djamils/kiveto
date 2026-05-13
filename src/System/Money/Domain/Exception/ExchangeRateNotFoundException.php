<?php

declare(strict_types=1);

namespace App\System\Money\Domain\Exception;

final class ExchangeRateNotFoundException extends \DomainException
{
    public function __construct(string $from, string $to, string $date)
    {
        parent::__construct(\sprintf('No exchange rate found for "%s/%s" on "%s".', $from, $to, $date));
    }
}
