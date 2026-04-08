<?php

declare(strict_types=1);

namespace App\System\AccessControl\Domain\ValueObject;

enum ClinicMembershipEngagement: string
{
    case EMPLOYEE   = 'EMPLOYEE';
    case CONTRACTOR = 'CONTRACTOR';
}
