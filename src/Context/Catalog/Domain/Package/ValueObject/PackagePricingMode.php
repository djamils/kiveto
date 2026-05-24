<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Package\ValueObject;

enum PackagePricingMode: string
{
    case FIXED_PRICE       = 'FIXED_PRICE';
    case SUM_OF_COMPONENTS = 'SUM_OF_COMPONENTS';
}
