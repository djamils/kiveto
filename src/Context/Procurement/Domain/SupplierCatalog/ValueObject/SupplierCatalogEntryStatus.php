<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierCatalog\ValueObject;

enum SupplierCatalogEntryStatus: string
{
    case ACTIVE       = 'ACTIVE';
    case DISCONTINUED = 'DISCONTINUED';
}
