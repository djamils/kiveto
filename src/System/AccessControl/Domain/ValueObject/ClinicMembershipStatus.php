<?php

declare(strict_types=1);

namespace App\System\AccessControl\Domain\ValueObject;

enum ClinicMembershipStatus: string
{
    case ACTIVE   = 'ACTIVE';
    case DISABLED = 'DISABLED';
}
