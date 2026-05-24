<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Act\ValueObject;

enum ActStatus: string
{
    case ACTIVE   = 'ACTIVE';
    case ARCHIVED = 'ARCHIVED';
}
