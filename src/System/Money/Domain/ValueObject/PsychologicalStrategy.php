<?php

declare(strict_types=1);

namespace App\System\Money\Domain\ValueObject;

/**
 * Variant of psychological rounding applied by PsychologicalRounding.
 *
 * NINETY_NINE : price ending in .99 (e.g. 19.47 → 19.99).
 * NINETY      : price ending in .90 (e.g. 19.47 → 19.90).
 * TEN_CENTS   : rounded up to the nearest 0.10 (e.g. 19.47 → 19.50).
 */
enum PsychologicalStrategy: string
{
    case NINETY_NINE = 'ninety_nine';
    case NINETY      = 'ninety';
    case TEN_CENTS   = 'ten_cents';
}
