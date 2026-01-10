<?php

declare(strict_types=1);

namespace App\Clinic\Domain\ValueObject;

enum ClinicGroupStatus: string
{
    case ACTIVE    = 'active';
    case SUSPENDED = 'suspended';
}
