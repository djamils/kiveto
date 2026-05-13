<?php

declare(strict_types=1);

namespace App\System\Money\Application\Query\GetExchangeRate;

use App\Shared\Application\Bus\QueryInterface;
use App\System\Money\Domain\ValueObject\ExchangeRatePair;

final readonly class GetExchangeRate implements QueryInterface
{
    public function __construct(
        public readonly ExchangeRatePair $pair,
        public readonly \DateTimeImmutable $date,
    ) {
    }
}
