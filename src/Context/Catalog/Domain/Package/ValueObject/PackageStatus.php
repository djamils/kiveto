<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Package\ValueObject;

enum PackageStatus: string
{
    case ACTIVE   = 'ACTIVE';
    case ARCHIVED = 'ARCHIVED';
}
