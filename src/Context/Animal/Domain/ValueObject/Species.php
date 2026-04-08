<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\ValueObject;

enum Species: string
{
    case DOG   = 'dog';
    case CAT   = 'cat';
    case NAC   = 'nac';
    case OTHER = 'other';
}
