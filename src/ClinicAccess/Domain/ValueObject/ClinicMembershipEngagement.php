<?php

declare(strict_types=1);

namespace App\ClinicAccess\Domain\ValueObject;

enum ClinicMembershipEngagement: string
{
    case EMPLOYEE   = 'EMPLOYEE';
    case CONTRACTOR = 'CONTRACTOR';
}
