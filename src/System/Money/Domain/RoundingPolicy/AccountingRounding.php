<?php

declare(strict_types=1);

namespace App\System\Money\Domain\RoundingPolicy;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\ValueObject\CurrencyDecimals;
use App\System\Money\Domain\ValueObject\RoundingPolicyId;

final class AccountingRounding implements RoundingPolicy
{
    public function id(): RoundingPolicyId
    {
        return RoundingPolicyId::ACCOUNTING;
    }

    /** @param numeric-string $amount */
    public function round(string $amount, CurrencyCode $currency, CurrencyDecimals $decimals): string
    {
        return bcround($amount, $decimals->value(), \RoundingMode::HalfEven);
    }
}
