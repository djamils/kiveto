<?php

declare(strict_types=1);

namespace App\System\Money\Application\Query\ConvertMoney;

use App\Shared\Application\Bus\QueryInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\RoundingPolicy\RoundingPolicy;
use App\System\Money\Domain\ValueObject\Money;

final readonly class ConvertMoney implements QueryInterface
{
    public function __construct(
        public readonly Money $amount,
        public readonly CurrencyCode $targetCurrency,
        public readonly \DateTimeImmutable $date,
        public readonly RoundingPolicy $rounding,
    ) {
    }
}
