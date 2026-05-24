<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing\ValueObject;

enum PriceRuleType: string
{
    case SPECIES_ADJUSTMENT  = 'SPECIES_ADJUSTMENT';
    case SIZE_ADJUSTMENT     = 'SIZE_ADJUSTMENT';
    case URGENCY_COEFFICIENT = 'URGENCY_COEFFICIENT';
    case DISCOUNT            = 'DISCOUNT';

    public function applicationOrder(): int
    {
        return match ($this) {
            PriceRuleType::SPECIES_ADJUSTMENT  => 10,
            PriceRuleType::SIZE_ADJUSTMENT     => 20,
            PriceRuleType::URGENCY_COEFFICIENT => 30,
            PriceRuleType::DISCOUNT            => 40,
        };
    }
}
