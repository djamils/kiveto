<?php

declare(strict_types=1);

namespace App\ClinicAccess\Domain\ValueObject;

enum ClinicMembershipStatus: string
{
    case ACTIVE   = 'ACTIVE';
    case DISABLED = 'DISABLED';
}
