<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing\ValueObject;

enum PriceListStatus: string
{
    case ACTIVE   = 'ACTIVE';
    case ARCHIVED = 'ARCHIVED';
}
