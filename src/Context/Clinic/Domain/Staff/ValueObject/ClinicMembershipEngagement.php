<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Staff\ValueObject;

enum ClinicMembershipEngagement: string
{
    case EMPLOYEE   = 'EMPLOYEE';
    case CONTRACTOR = 'CONTRACTOR';
}
