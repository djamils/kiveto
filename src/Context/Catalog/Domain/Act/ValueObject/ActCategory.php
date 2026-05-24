<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Act\ValueObject;

enum ActCategory: string
{
    case CONSULTATION    = 'CONSULTATION';
    case SURGERY         = 'SURGERY';
    case IMAGING         = 'IMAGING';
    case LABORATORY      = 'LABORATORY';
    case HOSPITALIZATION = 'HOSPITALIZATION';
    case VACCINATION     = 'VACCINATION';
    case PREVENTION      = 'PREVENTION';
    case OTHER           = 'OTHER';
}
