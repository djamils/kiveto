<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierPricing\ValueObject;

enum SupplierPricingSource: string
{
    case CATALOG_DEFAULT = 'CATALOG_DEFAULT';
    case NEGOTIATED      = 'NEGOTIATED';
    case IMPORTED        = 'IMPORTED';
}
