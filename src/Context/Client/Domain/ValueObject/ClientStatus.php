<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\ValueObject;

enum ClientStatus: string
{
    case ACTIVE   = 'active';
    case ARCHIVED = 'archived';
}
