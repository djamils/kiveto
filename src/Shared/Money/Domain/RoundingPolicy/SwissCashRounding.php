<?php

declare(strict_types=1);

namespace App\Shared\Money\Domain\RoundingPolicy;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\Exception\SwissCashRoundingRequiresChfException;
use App\Shared\Money\Domain\ValueObject\CurrencyDecimals;
use App\Shared\Money\Domain\ValueObject\RoundingPolicyId;

/**
 * Swiss cash rounding to the nearest 0.05 CHF multiple.
 *
 * Mandatory for cash transactions in Switzerland since the removal of 1 and 2
 * centime coins. Exclusively applicable to CHF; any other currency throws
 * SwissCashRoundingRequiresChfException.
 * Example: 1.425 → 1.45; 1.426 → 1.45; 1.475 → 1.50.
 */
final class SwissCashRounding implements RoundingPolicy
{
    public function id(): RoundingPolicyId
    {
        return RoundingPolicyId::SWISS_CASH;
    }

    /** @param numeric-string $amount */
    public function round(string $amount, CurrencyCode $currency, CurrencyDecimals $decimals): string
    {
        if ('CHF' !== $currency->toString()) {
            throw new SwissCashRoundingRequiresChfException($currency->toString());
        }

        return bcmul(
            bcround(bcdiv($amount, '0.05', 4), 0, \RoundingMode::HalfAwayFromZero),
            '0.05',
            $decimals->value(),
        );
    }
}
