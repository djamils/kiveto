<?php

declare(strict_types=1);

namespace App\Shared\Money\Domain\RoundingPolicy;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\CurrencyDecimals;
use App\Shared\Money\Domain\ValueObject\RoundingPolicyId;

/**
 * Commercial rounding: HALF_AWAY_FROM_ZERO (standard rounding).
 *
 * Equidistant values (x.5) are rounded towards the higher absolute value.
 * This is the behaviour expected by most end users.
 * Example: 1.245 → 1.25; 1.235 → 1.24.
 */
final class CommercialRounding implements RoundingPolicy
{
    public function id(): RoundingPolicyId
    {
        return RoundingPolicyId::COMMERCIAL;
    }

    /** @param numeric-string $amount */
    public function round(string $amount, CurrencyCode $currency, CurrencyDecimals $decimals): string
    {
        return bcround($amount, $decimals->value(), \RoundingMode::HalfAwayFromZero);
    }
}
