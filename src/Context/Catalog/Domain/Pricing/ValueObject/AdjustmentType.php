<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing\ValueObject;

enum AdjustmentType: string
{
    case COEFFICIENT           = 'COEFFICIENT';
    case FIXED_AMOUNT_DISCOUNT = 'FIXED_AMOUNT_DISCOUNT';
}
