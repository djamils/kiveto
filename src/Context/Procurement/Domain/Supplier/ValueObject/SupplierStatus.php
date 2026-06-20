<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\Supplier\ValueObject;

enum SupplierStatus: string
{
    case ACTIVE   = 'ACTIVE';
    case ARCHIVED = 'ARCHIVED';
}
