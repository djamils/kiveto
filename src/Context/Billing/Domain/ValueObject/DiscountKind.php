<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

enum DiscountKind: string
{
    case PERCENTAGE = 'PERCENTAGE';
    case FIXED      = 'FIXED';
}
