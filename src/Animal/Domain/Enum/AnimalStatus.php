<?php

declare(strict_types=1);

namespace App\Animal\Domain\Enum;

enum AnimalStatus: string
{
    case ACTIVE   = 'active';
    case ARCHIVED = 'archived';
}
