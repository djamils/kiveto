<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\ValueObject;

enum AnimalUsage: string
{
    case COMPANION = 'companion';
    case LIVESTOCK = 'livestock';
    case EQUINE    = 'equine';
}
