<?php

declare(strict_types=1);

namespace App\Shared\Money\Domain\RoundingPolicy;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\CurrencyDecimals;
use App\Shared\Money\Domain\ValueObject\RoundingPolicyId;

/**
 * Strategy for rounding a decimal monetary amount to the precision of a currency.
 *
 * Implementations must be stateless. round() always returns a numeric string
 * with at most $decimals decimal places. Implementations that are restricted
 * to a specific currency (e.g. SwissCashRounding) must throw when given an
 * incompatible currency; all others accept any currency.
 */
interface RoundingPolicy
{
    public function id(): RoundingPolicyId;

    /** @param numeric-string $amount */
    public function round(string $amount, CurrencyCode $currency, CurrencyDecimals $decimals): string;
}
