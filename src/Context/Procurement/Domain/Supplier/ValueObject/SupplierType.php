<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\Supplier\ValueObject;

enum SupplierType: string
{
    case CENTRALE   = 'CENTRALE';
    case LABORATORY = 'LABORATORY';
    case DIVERS     = 'DIVERS';
}
