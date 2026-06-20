<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierAccount\ValueObject;

enum SupplierAccountStatus: string
{
    case ACTIVE   = 'ACTIVE';
    case DISABLED = 'DISABLED';
}
