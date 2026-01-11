<?php

declare(strict_types=1);

namespace App\AccessControl\Domain\ValueObject;

enum ClinicMembershipStatus: string
{
    case ACTIVE   = 'ACTIVE';
    case DISABLED = 'DISABLED';
}
