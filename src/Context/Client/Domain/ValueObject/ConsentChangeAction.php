<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\ValueObject;

enum ConsentChangeAction: string
{
    case GRANTED   = 'granted';
    case WITHDRAWN = 'withdrawn';
}
