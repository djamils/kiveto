<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\ValueObject;

enum ContactLabel: string
{
    case MOBILE = 'mobile';
    case HOME   = 'home';
    case WORK   = 'work';
    case OTHER  = 'other';
}
