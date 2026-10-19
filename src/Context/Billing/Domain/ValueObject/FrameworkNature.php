<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

/**
 * What a document supplies, for its invoicing framework (BT-23): goods (B),
 * services (S) or both (M).
 */
enum FrameworkNature: string
{
    case GOODS    = 'B';
    case SERVICES = 'S';
    case MIXED    = 'M';
}
