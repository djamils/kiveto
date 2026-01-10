<?php

declare(strict_types=1);

namespace App\Clinic\Domain\ValueObject;

enum ClinicStatus: string
{
    case ACTIVE    = 'active';
    case SUSPENDED = 'suspended';
    case CLOSED    = 'closed';
}
