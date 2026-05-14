<?php

declare(strict_types=1);

namespace App\System\Money\Domain\RoundingPolicy;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\ValueObject\CurrencyDecimals;
use App\System\Money\Domain\ValueObject\RoundingPolicyId;

/**
 * Accounting rounding: HALF_EVEN (banker's rounding).
 *
 * Equidistant values (x.5) are rounded to the nearest even digit, eliminating
 * systematic bias over large transaction volumes.
 * Example: 1.245 → 1.24; 1.255 → 1.26.
 */
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
