<?php

declare(strict_types=1);

namespace App\System\Money\Domain\RoundingPolicy;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\ValueObject\CurrencyDecimals;
use App\System\Money\Domain\ValueObject\PsychologicalStrategy;
use App\System\Money\Domain\ValueObject\RoundingPolicyId;

final class PsychologicalRounding implements RoundingPolicy
{
    public function __construct(private readonly PsychologicalStrategy $strategy)
    {
    }

    public function id(): RoundingPolicyId
    {
        return RoundingPolicyId::PSYCHOLOGICAL;
    }

    /** @param numeric-string $amount */
    public function round(string $amount, CurrencyCode $currency, CurrencyDecimals $decimals): string
    {
        if (PsychologicalStrategy::TEN_CENTS === $this->strategy) {
            /** @var numeric-string $divided */
            $divided = bcdiv($amount, '0.10', 4);
            /** @var numeric-string $ceiled */
            $ceiled = bcceil($divided);

            return bcmul($ceiled, '0.10', $decimals->value());
        }

        /** @var numeric-string $floor */
        $floor  = bcfloor($amount);
        $addend = PsychologicalStrategy::NINETY_NINE === $this->strategy ? '0.99' : '0.90';

        return bcadd($floor, $addend, $decimals->value());
    }
}
