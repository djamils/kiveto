<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Staff\ValueObject;

enum ClinicMembershipStatus: string
{
    case ACTIVE   = 'ACTIVE';
    case DISABLED = 'DISABLED';
}
