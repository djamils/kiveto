<?php

declare(strict_types=1);

namespace App\Shared\Money\Domain\ValueObject;

/**
 * Identifies the rounding strategy to apply to a monetary amount.
 *
 * Each case maps to a concrete implementation of RoundingPolicy.
 */
enum RoundingPolicyId: string
{
    case ACCOUNTING = 'accounting';
    case COMMERCIAL = 'commercial';
    case SWISS_CASH = 'swiss_cash';
}
