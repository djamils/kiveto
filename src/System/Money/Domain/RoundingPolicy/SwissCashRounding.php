<?php

declare(strict_types=1);

namespace App\System\Money\Domain\RoundingPolicy;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\Exception\SwissCashRoundingRequiresChfException;
use App\System\Money\Domain\ValueObject\CurrencyDecimals;
use App\System\Money\Domain\ValueObject\RoundingPolicyId;

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
