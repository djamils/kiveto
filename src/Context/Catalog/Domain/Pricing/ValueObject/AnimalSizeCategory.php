<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing\ValueObject;

enum AnimalSizeCategory: string
{
    case XS = 'XS';
    case S  = 'S';
    case M  = 'M';
    case L  = 'L';
    case XL = 'XL';
}
