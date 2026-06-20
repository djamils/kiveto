<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\Supplier\ValueObject;

enum SupplierIntegrationMode: string
{
    case AUTOMATIC     = 'AUTOMATIC';
    case MANUAL_EXPORT = 'MANUAL_EXPORT';
    case SIMULATION    = 'SIMULATION';
}
