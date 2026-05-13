<?php

declare(strict_types=1);

namespace App\System\Money\Domain\RoundingPolicy;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\ValueObject\CurrencyDecimals;
use App\System\Money\Domain\ValueObject\RoundingPolicyId;

interface RoundingPolicy
{
    public function id(): RoundingPolicyId;

    /** @param numeric-string $amount */
    public function round(string $amount, CurrencyCode $currency, CurrencyDecimals $decimals): string;
}
